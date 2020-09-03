<?php

namespace Tests\Unit\Reactors;


use App\FeatureFlags;
use App\Jobs\AddCustomerToSlackChannel;
use App\Jobs\AddCustomerToSlackUserGroup;
use App\Jobs\DemoteMemberToPublicOnlyMemberInSlack;
use App\Jobs\InviteCustomerPublicOnlyMemberInSlack;
use App\Jobs\MakeCustomerRegularMemberInSlack;
use App\Jobs\RemoveCustomerFromSlackChannel;
use App\Jobs\RemoveCustomerFromSlackUserGroup;
use App\Reactors\SlackReactor;
use App\StorableEvents\CustomerBecameBoardMember;
use App\StorableEvents\CustomerRemovedFromBoard;
use App\StorableEvents\MembershipActivated;
use App\StorableEvents\MembershipDeactivated;
use App\StorableEvents\SubscriptionUpdated;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use YlsIdeas\FeatureFlags\Facades\Features;

class SlackReactorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->withOnlyEventHandler(SlackReactor::class);
    }

    /** @test */
    public function on_becoming_board_member_customer_is_added_to_board_slack_channel_and_group()
    {
        Bus::fake([AddCustomerToSlackChannel::class, AddCustomerToSlackUserGroup::class]);

        $customerId = 1;
        event(new CustomerBecameBoardMember($customerId));

        Bus::assertDispatched(AddCustomerToSlackChannel::class,
            function (AddCustomerToSlackChannel $job) use ($customerId) {
            return $job->customerId == $customerId && $job->channel == "board";
        });

        Bus::assertDispatched(AddCustomerToSlackUserGroup::class,
            function (AddCustomerToSlackUserGroup $job) use ($customerId) {
                return $job->customerId == $customerId && $job->usergroupHandle == "theboard";
            });
    }

    /** @test */
    public function on_removal_from_board_customer_is_removed_from_board_slack_channel_and_group()
    {
        Bus::fake([RemoveCustomerFromSlackChannel::class, RemoveCustomerFromSlackUserGroup::class]);

        $customerId = 1;
        event(new CustomerRemovedFromBoard($customerId));

        Bus::assertDispatched(RemoveCustomerFromSlackChannel::class,
            function (RemoveCustomerFromSlackChannel $job) use ($customerId) {
                return $job->customerId == $customerId && $job->channel == "board";
            });

        Bus::assertDispatched(RemoveCustomerFromSlackUserGroup::class,
            function (RemoveCustomerFromSlackUserGroup $job) use ($customerId) {
                return $job->customerId == $customerId && $job->usergroupHandle == "theboard";
            });
    }

    /** @test */
    public function on_membership_deactivation_they_are_demoted_in_slack()
    {
        Bus::fake(DemoteMemberToPublicOnlyMemberInSlack::class);

        $customerId = 1;
        event(new MembershipDeactivated($customerId));

        Bus::assertDispatched(DemoteMemberToPublicOnlyMemberInSlack::class,
        function (DemoteMemberToPublicOnlyMemberInSlack $job) use ($customerId) {
            return $job->wooCustomerId == $customerId;
        });
    }

    /** @test */
    public function on_membership_deactivation_with_keep_members_flag_on_they_are_not_demoted()
    {
        Bus::fake(DemoteMemberToPublicOnlyMemberInSlack::class);

        Features::turnOn(FeatureFlags::KEEP_MEMBERS_IN_SLACK_AND_EMAIL);

        event(new MembershipDeactivated(1));

        Bus::assertNotDispatched(DemoteMemberToPublicOnlyMemberInSlack::class);
    }

    /** @test */
    public function on_membership_activation_they_are_made_a_regular_member_in_slack()
    {
        Bus::fake(MakeCustomerRegularMemberInSlack::class);

        $customerId = 1;
        event(new MembershipActivated($customerId));

        Bus::assertDispatched(MakeCustomerRegularMemberInSlack::class,
            function (MakeCustomerRegularMemberInSlack $job) use ($customerId) {
                return $job->wooCustomerId == $customerId;
            });
    }

    /** @test */
    public function need_id_check_subscription_invites_as_public_only_member()
    {
        Bus::fake(InviteCustomerPublicOnlyMemberInSlack::class);

        $subscription = $this->subscription()->status('need-id-check');

        event(new SubscriptionUpdated($subscription->toArray()));

        Bus::assertDispatched(InviteCustomerPublicOnlyMemberInSlack::class,
            function (InviteCustomerPublicOnlyMemberInSlack $job) use ($subscription) {
                return $job->wooCustomerId == $subscription->customer_id;
            });
    }

    /** @test */
    public function need_id_check_subscription_invites_regular_member_if_flag_is_set()
    {
        Bus::fake(MakeCustomerRegularMemberInSlack::class);

        Features::turnOn(FeatureFlags::NEED_ID_CHECK_GETS_ADDED_TO_SLACK_AND_EMAIL);

        $subscription = $this->subscription()->status('need-id-check');

        event(new SubscriptionUpdated($subscription->toArray()));

        Bus::assertDispatched(MakeCustomerRegularMemberInSlack::class,
            function (MakeCustomerRegularMemberInSlack $job) use ($subscription) {
                return $job->wooCustomerId == $subscription->customer_id;
            });
    }
}
