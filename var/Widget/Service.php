<?php

namespace Widget;

use Typecho\Common;
use Typecho\Http\Client;
use Typecho\Response;
use Typecho\Widget\Exception;
use Widget\Base\Contents;
use Widget\Base\Options as BaseOptions;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Thành phần dịch vụ không đồng bộ phổ quát
 *
 * @author qining
 * @category typecho
 * @package Widget
 */
class Service extends BaseOptions implements ActionInterface
{
    /**
     * Yêu cầu không đồng bộ
     *
     * @var array
     */
    public $asyncRequests = [];

    /**
     * Gửi triển khai pingback
     *
     * @throws Exception|Client\Exception
     */
    public function sendPingHandle()
    {
        /** Xác minh quyền */
        $data = $this->request->get('@json');
        $token = $data['token'] ?? '';
        $permalink = $data['permalink'];
        $title = $data['title'];
        $excerpt = $data['excerpt'];

        $response = ['trackback' => [], 'pingback' => []];

        if (!Common::timeTokenValidate($token, $this->options->secret, 3) || empty($permalink)) {
            throw new Exception(_t('Quyền truy cập bị cấm!'), 403);
        }

        /** Bỏ qua thời gian chờ */
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(30);
        }

        if (!empty($data['pingback'])) {
            $links = $data['pingback'];
            $permalinkPart = parse_url($permalink);

            /** gửi pingback */
            foreach ($links as $url) {
                $urlPart = parse_url($url);

                if (isset($urlPart['scheme'])) {
                    if ('http' != $urlPart['scheme'] && 'https' != $urlPart['scheme']) {
                        continue;
                    }
                } else {
                    $urlPart['scheme'] = 'http';
                    $url = Common::buildUrl($urlPart);
                }

                if ($permalinkPart['host'] == $urlPart['host'] && $permalinkPart['path'] == $urlPart['path']) {
                    continue;
                }

                $spider = Client::get();

                if ($spider) {
                    $spider->setTimeout(10)
                        ->send($url);

                    if (!($xmlrpcUrl = $spider->getResponseHeader('x-pingback'))) {
                        if (
                            preg_match(
                                "/<link[^>]*rel=[\"']pingback[\"'][^>]*href=[\"']([^\"']+)[\"'][^>]*>/i",
                                $spider->getResponseBody(),
                                $out
                            )
                        ) {
                            $xmlrpcUrl = $out[1];
                        }
                    }

                    if (!empty($xmlrpcUrl)) {
                        $response['pingback'][] = $url;

                        try {
                            $xmlrpc = new \IXR\Client($xmlrpcUrl);
                            $xmlrpc->pingback->ping($permalink, $url);
                            unset($xmlrpc);
                        } catch (\IXR\Exception $e) {
                            continue;
                        }
                    }
                }

                unset($spider);
            }
        }

        /** Gửi trackback */
        if (!empty($data['trackback'])) {
            $links = $data['trackback'];

            foreach ($links as $url) {
                $client = Client::get();
                $response['trackback'][] = $url;

                if ($client) {
                    try {
                        $client->setTimeout(5)
                            ->setData([
                                'blog_name' => $this->options->title . ' &raquo ' . $title,
                                'url' => $permalink,
                                'excerpt' => $excerpt
                            ])
                            ->send($url);

                        unset($client);
                    } catch (Client\Exception $e) {
                        continue;
                    }
                }
            }
        }

        $this->response->throwJson($response);
    }

    /**
     * gửi pingback
     * <code>
     * $this->sendPing($post);
     * </code>
     *
     * @param Contents $content 内容url
     * @param array|null $trackback
     */
    public function sendPing(Contents $content, ?array $trackback = null)
    {
        $this->user->pass('contributor');

        if ($client = Client::get()) {
            try {
                $input = [
                    'do' => 'ping',
                    'permalink' => $content->permalink,
                    'excerpt' => $content->excerpt,
                    'title' => $content->title,
                    'token' => Common::timeToken($this->options->secret)
                ];

                if (preg_match_all("|<a[^>]*href=[\"'](.*?)[\"'][^>]*>(.*?)</a>|", $content->content, $matches)) {
                    $pingback = array_unique($matches[1]);

                    if (!empty($pingback)) {
                        $input['pingback'] = $pingback;
                    }
                }

                if (!empty($trackback)) {
                    $input['trackback'] = $trackback;
                }

                $client->setHeader('User-Agent', $this->options->generator)
                    ->setTimeout(2)
                    ->setJson($input)
                    ->send($this->getServiceUrl('ping'));
            } catch (Client\Exception $e) {
                return;
            }
        }
    }

    /**
     * Nhận URL thực
     *
     * @param string $do tên hành động
     * @return string
     */
    private function getServiceUrl(string $do): string
    {
        $url = Common::url('/action/service', $this->options->index);

        if (defined('__TYPECHO_SERVICE_URL__')) {
            $rootPath = rtrim(parse_url($this->options->rootUrl, PHP_URL_PATH), '/');
            $path = parse_url($url, PHP_URL_PATH);
            $parts = parse_url(__TYPECHO_SERVICE_URL__);

            if (
                !empty($parts['path'])
                && $parts['path'] != '/'
                && rtrim($parts['path'], '/') != $rootPath
            ) {
                $path = Common::url($path, $parts['path']);
            }

            $parts['path'] = $path;
            $url = Common::buildUrl($parts);
        }

        return $url . '?do=' . $do;
    }

    /**
     * Yêu cầu dịch vụ không đồng bộ
     *
     * @param $method
     * @param mixed $params
     */
    public function requestService($method, $params = null)
    {
        static $called;

        if (!$called) {
            Response::getInstance()->addResponder(function () {
                if (!empty($this->asyncRequests) && $client = Client::get()) {
                    try {
                        $client->setHeader('User-Agent', $this->options->generator)
                            ->setTimeout(2)
                            ->setJson([
                                'requests' => $this->asyncRequests,
                                'token' => Common::timeToken($this->options->secret)
                            ])
                            ->send($this->getServiceUrl('async'));
                    } catch (Client\Exception $e) {
                        return;
                    }
                }
            });

            $called = true;
        }

        $this->asyncRequests[] = [$method, $params];
    }

    /**
     * Thực hiện gọi lại
     *
     * @throws Exception
     */
    public function asyncHandle()
    {
        /** Xác minh quyền */
        $data = $this->request->get('@json');
        $token = $data['token'] ?? '';

        if (!Common::timeTokenValidate($token, $this->options->secret, 3)) {
            throw new Exception(_t('Không có quyền truy cập!'), 403);
        }

        /** Bỏ qua thời gian chờ */
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(30);
        }

        $requests = $data['requests'] ?? null;
        $plugin = self::pluginHandle();

        if (!empty($requests)) {
            foreach ($requests as $request) {
                [$method, $params] = $request;
                $plugin->{$method}($params);
            }
        }
    }

    /**
     * Mục nhập yêu cầu không đồng bộ
     */
    public function action()
    {
        $this->on($this->request->isPost() && $this->request->is('do=ping'))->sendPingHandle();
        $this->on($this->request->isPost() && $this->request->is('do=async'))->asyncHandle();
    }
}
