<?php

namespace App\Slack\Modals;

use App\Http\Requests\SlackRequest;
use App\Slack\SlackApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait ModalTrait
{

    public function push()
    {
        return response()->json([
            'response_action' => 'push',
            'view' => $this,
        ]);
    }

    /**
     * This method can't be used except on "Submit" so it can't be used in a block action response. Use updateViaApi
     * instead.
     *
     * See https://api.slack.com/surfaces/modals/using#updating_response
     *
     * @return JsonResponse
     */
    public function update()
    {
        return response()->json([
            'response_action' => 'update',
            'view' => $this,
        ]);
    }

    /**
     * This method uses the slack api to respond and handles the view id/hash as well as returning an OK for the api
     * to all be happy. You MUST use this method in response to a block action which is why the request is passed in.
     *
     * If you need to call update on an existing view id, just use the SlackApi class directly.
     *
     * @param SlackRequest $request
     * @return Response
     */
    public function updateViaApi(SlackRequest $request)
    {
        $payload = $request->payload();
        $view = $payload['view'];
        $view_id = $view['id'];
        $view_hash = $view['hash'];

        /** @var SlackApi $api */
        $api = app(SlackApi::class);
        $api->views->update($view_id, $this, $view_hash);

        return response('');
    }

    public function open($trigger_id)
    {
        /** @var SlackApi $slackApi */
        $slackApi = app(SlackApi::class);

        return $slackApi->views->open($trigger_id, $this);
    }

    protected static function clearViewStack()
    {
        return response()->json([
            'response_action' => 'clear',
        ]);
    }

    /**
     * Get state values from the slack request if there are any.
     *
     * @param SlackRequest $request
     */
    protected static function getStateValues(SlackRequest $request): array
    {
        $payload = $request->payload();

        if (is_null($payload)) {
            return [];
        }

        if (!array_key_exists('view', $payload)) {
            return [];
        }
        $view = $payload['view'];

        if (!array_key_exists('state', $view)) {
            return [];
        }
        $state = $view['state'];

        if (!array_key_exists('values', $state)) {
            return [];
        }
        $values = $state['values'];

        $result = [];

        foreach ($values as $blockId => $blockValues) {
            $result[$blockId] = [];
            foreach ($blockValues as $actionId => $actionValues) {
                if (array_key_exists('selected_option', $actionValues)) {
                    $selected = $actionValues['selected_option'];
                    if (empty($selected)) {
                        $result[$blockId][$actionId] = null;
                    } else {
                        $result[$blockId][$actionId] = $selected['value'];
                    }
                }
            }
        }

        return $result;
    }
}
