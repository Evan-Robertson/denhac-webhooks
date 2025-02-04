<?php

namespace App\Slack;

use App\Slack\Api\ChatApi;
use App\Slack\Api\ConversationsApi;
use App\Slack\Api\SlackClients;
use App\Slack\Api\TeamApi;
use App\Slack\Api\UsergroupsApi;
use App\Slack\Api\UsersApi;
use App\Slack\Api\ViewsApi;
use JetBrains\PhpStorm\Pure;

/**
 * @property ChatApi chat
 * @property ConversationsApi conversations
 * @property TeamApi team
 * @property UsergroupsApi usergroups
 * @property UsersApi users
 * @property ViewsApi views
 */
class SlackApi
{
    public const PUBLIC_CHANNEL = 'public_channel';
    public const PRIVATE_CHANNEL = 'private_channel';
    public const MULTI_PARTY_MESSAGE = 'mpim';
    public const DIRECT_MESSAGE = 'im';

    private SlackClients $clients;

    #[Pure] public function __construct()
    {
        $this->clients = new SlackClients();
    }

    #[Pure] public function __get(string $name)
    {
        if ($name == 'chat') {
            return new ChatApi($this->clients);
        } else if ($name == 'conversations') {
            return new ConversationsApi($this->clients);
        } else if ($name == 'team') {
            return new TeamApi($this->clients);
        } else if ($name == 'usergroups') {
            return new UsergroupsApi($this->clients);
        } else if ($name == 'users') {
            return new UsersApi($this->clients);
        } else if ($name == 'views') {
            return new ViewsApi($this->clients);
        }

        return null;
    }

}
