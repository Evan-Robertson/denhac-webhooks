<?php

namespace App\Http\Controllers;

use App\Http\Requests\SlackRequest;
use App\Slack\CommonResponses;
use App\Slack\Modals\MembershipOptionsModal;
use SlackPhp\BlockKit\Kit;

class SlackMembershipCommandController extends Controller
{
    public function __invoke(SlackRequest $request)
    {
        $customer = $request->customer();

        if ($customer === null) {
            return Kit::newMessage()->text(CommonResponses::unrecognizedUser());
        }

        $modal = new MembershipOptionsModal();
        $modal->open($request->get('trigger_id'));

        return response('');
    }
}
