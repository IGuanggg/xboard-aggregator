<?php

namespace Plugin\AirportAggregator;

use App\Services\Plugin\AbstractPlugin;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Plugin\AirportAggregator\Services\ClientResolver;
use Plugin\AirportAggregator\Services\GroupRouteResolver;
use Plugin\AirportAggregator\Services\SublinkClient;
use Throwable;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        if (!$this->getConfig('enabled', true)) {
            return;
        }

        $this->listen('client.subscribe.before', function ($payload = null): void {
            $request = request();
            if (!$request instanceof Request) {
                return;
            }

            $user = $request->user();
            if (!$user || !(new UserService())->isAvailable($user)) {
                // Let Xboard return its normal 403 response for expired or banned users.
                return;
            }

            $shareToken = (new GroupRouteResolver())->resolve(
                $this->getConfig('group_routes', '{}'),
                (int) $user->group_id
            );
            if ($shareToken === null) {
                // Groups without a mapping keep the native Xboard subscription.
                return;
            }

            try {
                $client = (new ClientResolver())->resolve(
                    $request,
                    $this->getConfig('client_map', '{}')
                );

                $result = (new SublinkClient(
                    baseUrl: (string) $this->getConfig('sublink_base_url', 'http://sublinkpro:8000'),
                    timeoutSeconds: (int) $this->getConfig('timeout_seconds', 15),
                    cacheSeconds: (int) $this->getConfig('cache_seconds', 30),
                    verifyTls: (bool) $this->getConfig('verify_tls', true),
                ))->subscription($shareToken, $client, (string) $request->userAgent());

                $headers = $result->headers;
                $headers['subscription-userinfo'] = sprintf(
                    'upload=%d; download=%d; total=%d; expire=%d',
                    (int) $user->u,
                    (int) $user->d,
                    (int) $user->transfer_enable,
                    (int) ($user->expired_at ?? 0),
                );
                $subscriptionResponse = response($result->body, $result->status, $headers);
            } catch (Throwable $exception) {
                Log::error('Airport Aggregator failed to fetch subscription', [
                    'group_id' => (int) $user->group_id,
                    'error' => $exception->getMessage(),
                ]);

                if ((bool) $this->getConfig('fail_open', false)) {
                    return;
                }

                $this->intercept(response(
                    '聚合订阅暂时不可用，请稍后重试',
                    502,
                    ['content-type' => 'text/plain; charset=utf-8']
                ));
            }

            // AbstractPlugin::intercept() intentionally throws a control-flow
            // exception. Keep it outside the transport catch block so a
            // successful subscription is not converted into a 502 response.
            $this->intercept($subscriptionResponse);
        }, 10);
    }
}
