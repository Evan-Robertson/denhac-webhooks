<?php

namespace App\Slack\Modals;

use App\Actions\NewMemberCardSlackLiveView;
use App\Customer;
use App\Http\Requests\SlackRequest;
use App\NewMemberCardActivation;
use App\Subscription;
use App\WooCommerce\Api\WooCommerceApi;
use Carbon\Carbon;
use Illuminate\Support\Str;
use SlackPhp\BlockKit\Kit;
use SlackPhp\BlockKit\Surfaces\Modal;

class NewMemberIdCheckModal implements ModalInterface
{
    use ModalTrait;

    private const FIRST_NAME = 'first-name';
    private const LAST_NAME = 'last-name';
    private const BIRTHDAY = 'birthday';
    private const CARD_NUM = 'card-num';

    private Modal $modalView;

    public function __construct($customer_id)
    {
        /** @var Customer $customer */
        $customer = Customer::whereWooId($customer_id)->first();

        $this->modalView = Kit::newModal()
            ->callbackId(self::callbackId())
            ->title('New Member Signup')
            ->clearOnClose(true)
            ->close('Cancel')
            ->submit('Submit')
            ->privateMetadata($customer->woo_id);

        $this->modalView->newInput()
            ->blockId(self::FIRST_NAME)
            ->label('First Name')
            ->newTextInput(self::FIRST_NAME)
            ->initialValue($customer->first_name);

        $this->modalView->newInput()
            ->blockId(self::LAST_NAME)
            ->label('Last Name')
            ->newTextInput(self::LAST_NAME)
            ->initialValue($customer->last_name);

        $birthdayInput = $this->modalView->newInput()
            ->blockId(self::BIRTHDAY)
            ->label('Birthday')
            ->newDatePicker(self::BIRTHDAY);

        if (! is_null($customer->birthday)) {
            $birthdayInput->initialDate($customer->birthday->format('Y-m-d'));
        }

        $cardsInput = $this->modalView->newInput()
            ->blockId(self::CARD_NUM)
            ->label('Card Number')
            ->newTextInput(self::CARD_NUM)
            ->placeholder('Enter Card Number');

        $this->modalView->newSection()
            ->plainText("The numbers on the card will look like \"01234 3300687-1\" and you should enter \"01234\""
                . " in this field.");

        $cardString = $customer->cards->implode('number', ',');
        if (! empty($cardString)) {
            $cardsInput->initialValue($cardString);
        }
    }

    public static function callbackId(): string
    {
        return 'membership-new-member-id-check-modal';
    }

    public static function handle(SlackRequest $request)
    {
        $view = $request->payload()['view'];
        $viewId = $view['id'];
        $firstName = $view['state']['values'][self::FIRST_NAME][self::FIRST_NAME]['value'];
        $lastName = $view['state']['values'][self::LAST_NAME][self::LAST_NAME]['value'];
        $birthday = Carbon::parse($view['state']['values'][self::BIRTHDAY][self::BIRTHDAY]['selected_date']);
        $card = $view['state']['values'][self::CARD_NUM][self::CARD_NUM]['value'];

        $errors = [];

        if ($birthday > Carbon::today()->subYears(18)) {
            $errors[self::BIRTHDAY] = 'New member is not at least 18';
        }

        if (preg_match("/^\d+$/", $card) == 0) {
            $errors[self::CARD_NUM] = 'Card should be a number';
        }

        if (! empty($errors)) {
            return response()->json([
                'response_action' => 'errors',
                'errors' => $errors,
            ]);
        }

        $customerId = $view['private_metadata'];

        $wooCommerceApi = app(WooCommerceApi::class);

        $wooCommerceApi->customers
            ->update($customerId, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'meta_data' => [
                    [
                        'key' => 'access_card_number',
                        'value' => $card,
                    ],
                    [
                        'key' => 'account_birthday',
                        'value' => $birthday->format('Y-m-d'),
                    ],
                    [
                        'key' => 'id_was_checked_by',
                        'value' => $request->customer()->woo_id,
                    ],
                    [
                        'key' => 'id_was_checked_when',
                        'value' => Carbon::now(),
                    ],
                    [
                        'key' => 'id_was_checked',
                        'value' => true,
                    ]
                ],
            ]);

        $newMemberCardActivation = NewMemberCardActivation::create([
            'woo_customer_id' => $customerId,
            'card_number' => ltrim($card, '0'),
            'state' => NewMemberCardActivation::SUBMITTED,
        ]);

        app(NewMemberCardSlackLiveView::class)
            ->onQueue()
            ->execute($viewId, $newMemberCardActivation);

        return (new NewMemberCardActivationLiveModal())->placeholder()->update();

        //return (new SuccessModal())->update();
    }

    public static function getOptions(SlackRequest $request)
    {
        return [];
    }

    public function jsonSerialize()
    {
        return $this->modalView->jsonSerialize();
    }
}
