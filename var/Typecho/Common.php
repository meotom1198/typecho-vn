<?php

namespace {

    use Typecho\I18n;

    /**
     * @deprecated use json_encode and json_decode directly
     */
    class Json
    {
        /**
         * @param $value
         * @return string
         */
        public static function encode($value): string
        {
            return json_encode($value);
        }

        /**
         * @param string $string
         * @param bool $assoc
         * @return mixed
         */
        public static function decode(string $string, bool $assoc = false)
        {
            return json_decode($string, $assoc);
        }
    }

    /**
     * I18n function
     *
     * @param string $string Văn bản cần dịch
     * @param mixed ...$args tham số
     *
     * @return string
     */
    function _t(string $string, ...$args): string
    {
        if (empty($args)) {
            return I18n::translate($string);
        } else {
            return vsprintf(I18n::translate($string), $args);
        }
    }

    /**
     * I18n function, translate and echo
     *
     * @param string $string Văn bản cần dịch
     * @param mixed ...$args tham số
     */
    function _e(string $string, ...$args)
    {
        array_unshift($args, $string);
        echo call_user_func_array('_t', $args);
    }

    /**
     * Hàm dịch cho dạng số nhiều
     *
     * @param string $single Dịch dạng số ít
     * @param string $plural Dịch dạng số nhiều
     * @param integer $number con số
     *
     * @return string
     */
    function _n(string $single, string $plural, int $number): string
    {
        return str_replace('%d', $number, I18n::ngettext($single, $plural, $number));
    }
}

namespace Typecho {
    const PLUGIN_NAMESPACE = 'TypechoPlugin';

    spl_autoload_register(function (string $className) {
        $isDefinedAlias = defined('__TYPECHO_CLASS_ALIASES__');
        $isNamespace = strpos($className, '\\') !== false;
        $isAlias = $isDefinedAlias && isset(__TYPECHO_CLASS_ALIASES__[$className]);
        $isPlugin = false;

        // detect if class is predefined
        if ($isNamespace) {
            $isPlugin = strpos(ltrim($className, '\\'), PLUGIN_NAMESPACE . '\\') !== false;

            if ($isPlugin) {
                $realClassName = substr($className, strlen(PLUGIN_NAMESPACE) + 1);
                $alias = Common::nativeClassName($realClassName);
                $path = str_replace('\\', '/', $realClassName);
            } else {
                if ($isDefinedAlias) {
                    $alias = array_search('\\' . ltrim($className, '\\'), __TYPECHO_CLASS_ALIASES__);
                }

                $alias = empty($alias) ? Common::nativeClassName($className) : $alias;
                $path = str_replace('\\', '/', $className);
            }
        } elseif (strpos($className, '_') !== false || $isAlias) {
            $isPlugin = !$isAlias && !preg_match("/^(Typecho|Widget|IXR)_/", $className);

            if ($isPlugin) {
                $alias = '\\TypechoPlugin\\' . str_replace('_', '\\', $className);
                $path = str_replace('_', '/', $className);
            } else {
                $alias = $isAlias ? __TYPECHO_CLASS_ALIASES__[$className]
                    : '\\' . str_replace('_', '\\', $className);

                $path = str_replace('\\', '/', $alias);
            }
        } else {
            $path = $className;
        }

        if (
            isset($alias)
            && (class_exists($alias, false)
                || interface_exists($alias, false)
                || trait_exists($alias, false))
        ) {
            class_alias($alias, $className, false);
            return;
        }

        // load class file
        $path .= '.php';
        $defaultFile = __TYPECHO_ROOT_DIR__ . '/var/' . $path;

        if (file_exists($defaultFile) && !$isPlugin) {
            include_once $defaultFile;
        } else {
            $pluginFile = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__ . '/' . $path;

            if (file_exists($pluginFile)) {
                include_once $pluginFile;
            } else {
                return;
            }
        }

        if (isset($alias)) {
            $classLoaded = class_exists($className, false)
                || interface_exists($className, false)
                || trait_exists($className, false);

            $aliasLoaded = class_exists($alias, false)
                || interface_exists($alias, false)
                || trait_exists($alias, false);

            if ($classLoaded && !$aliasLoaded) {
                class_alias($className, $alias);
            } elseif ($aliasLoaded && !$classLoaded) {
                class_alias($alias, $className);
            }
        }
    });

    /**
     * Phương pháp công khai Typecho
     *
     * @category typecho
     * @package Common
     * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
     * @license GNU General Public License 2.0
     */
    class Common
    {
        /** Phiên bản Typecho  */
        public const VERSION = '1.2.1';

        /**
         * Chuyển đổi đường dẫn thành liên kết
         *
         * @access public
         *
         * @param string|null $path con đường
         * @param string|null $prefix tiền tố
         *
         * @return string
         */
        public static function url(?string $path, ?string $prefix): string
        {
            $path = $path ?? '';
            $path = (0 === strpos($path, './')) ? substr($path, 2) : $path;
            return rtrim($prefix ?? '', '/') . '/'
                . str_replace('//', '/', ltrim($path, '/'));
        }

        /**
         * Phương pháp khởi tạo chương trình
         *
         * @access public
         * @return void
         */
        public static function init()
        {
            // init response
            Response::getInstance()->enableAutoSendHeaders(false);

            ob_start(function ($content) {
                Response::getInstance()->sendHeaders();
                return $content;
            });

            /** Đặt chức năng chặn ngoại lệ */
            set_exception_handler(function (\Throwable $exception) {
                echo '<pre><code>';
                echo '<h1>' . htmlspecialchars($exception->getMessage()) . '</h1>';
                echo htmlspecialchars($exception->__toString());
                echo '</code></pre>';
                exit;
            });
        }

        /**
         * Trang lỗi đầu ra
         *
         * @param \Throwable $exception thông báo lỗi
         */
        public static function error(\Throwable $exception)
        {
            $code = $exception->getCode() ?: 500;
            $message = $exception->getMessage();

            if ($exception instanceof \Typecho\Db\Exception) {
                $code = 500;

                // Ghi đè thông báo lỗi ban đầu
                $message = 'Database Server Error';

                if ($exception instanceof \Typecho\Db\Adapter\ConnectionException) {
                    $code = 503;
                    $message = 'Error establishing a database connection';
                } elseif ($exception instanceof \Typecho\Db\Adapter\SQLException) {
                    $message = 'Database Query Error';
                }
            }

            /** Đặt mã http */
            if (is_numeric($code) && $code > 200) {
                Response::getInstance()->setStatus($code);
            }

            $message = nl2br($message);

            if (defined('__TYPECHO_EXCEPTION_FILE__')) {
                require_once __TYPECHO_EXCEPTION_FILE__;
            } else {
                echo
                <<<EOF
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="UTF-8">
        <title>{$code}</title>
        <style>
            html {
                padding: 50px 10px;
                font-size: 16px;
                line-height: 1.4;
                color: #666;
                background: #F6F6F3;
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
            }

            html,
            input { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
            body {
                max-width: 500px;
                _width: 500px;
                padding: 30px 20px;
                margin: 0 auto;
                background: #FFF;
            }
            ul {
                padding: 0 0 0 40px;
            }
            .container {
                max-width: 380px;
                _width: 380px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            {$message}
        </div>
    </body>
</html>
EOF;
            }

            exit(1);
        }

        /**
         * @param string $className Tên lớp
         * @return boolean
         * @deprecated
         */
        public static function isAvailableClass(string $className): bool
        {
            return class_exists($className);
        }

        /**
         * @param array $value
         * @param $key
         *
         * @return array
         * @deprecated use array_column instead
         *
         */
        public static function arrayFlatten(array $value, $key): array
        {
            return array_column($value, $key);
        }

        /**
         * @param string $className
         * @return string
         */
        public static function nativeClassName(string $className): string
        {
            return trim(str_replace('\\', '_', $className), '_');
        }

        /**
         * Ký tự đầu ra theo số đếm
         * <code>
         * echo splitByCount(20, 10, 20, 30, 40, 50);
         * </code>
         *
         * @param int $count
         * @param int ...$sizes
         * @return int
         */
        public static function splitByCount(int $count, int ...$sizes): int
        {
            foreach ($sizes as $size) {
                if ($count < $size) {
                    return $size;
                }
            }

            return 0;
        }

        /**
         * Chức năng sửa chữa html tự đóng
         * Cách sử dụng:
         * <code>
         * $input = 'Đây là một văn bản html bị cắt ngắn<a href="#"';
         * echo Common::fixHtml($input);
         * //output: Đây là một văn bản html bị cắt ngắn
         * </code>
         *
         * @param string|null $string Dây cần sửa chữa
         * @return string|null
         */
        public static function fixHtml(?string $string): ?string
        {
            // Đóng thẻ tự đóng
            $startPos = strrpos($string, "<");

            if (false == $startPos) {
                return $string;
            }

            $trimString = substr($string, $startPos);

            if (false === strpos($trimString, ">")) {
                $string = substr($string, 0, $startPos);
            }

            // Danh sách thẻ html không tự đóng
            preg_match_all("/<([_0-9a-zA-Z-\:]+)\s*([^>]*)>/is", $string, $startTags);
            preg_match_all("/<\/([_0-9a-zA-Z-\:]+)>/is", $string, $closeTags);

            if (!empty($startTags[1]) && is_array($startTags[1])) {
                krsort($startTags[1]);
                $closeTagsIsArray = is_array($closeTags[1]);
                foreach ($startTags[1] as $key => $tag) {
                    $attrLength = strlen($startTags[2][$key]);
                    if ($attrLength > 0 && "/" == trim($startTags[2][$key][$attrLength - 1])) {
                        continue;
                    }

                    // danh sách trắng
                    if (
                        preg_match(
                            "/^(area|base|br|col|embed|hr|img|input|keygen|link|meta|param|source|track|wbr)$/i",
                            $tag
                        )
                    ) {
                        continue;
                    }

                    if (!empty($closeTags[1]) && $closeTagsIsArray) {
                        if (false !== ($index = array_search($tag, $closeTags[1]))) {
                            unset($closeTags[1][$index]);
                            continue;
                        }
                    }
                    $string .= "</{$tag}>";
                }
            }

            return preg_replace("/\<br\s*\/\>\s*\<\/p\>/is", '</p>', $string);
        }

        /**
         * Xóa thẻ html khỏi chuỗi
         * Cách sử dụng:
         * <code>
         * $input = '<a href="http://test/test.php" title="example">hello</a>';
         * $output = Common::stripTags($input, <a href="">);
         * echo $output;
         * //display: '<a href="http://test/test.php">hello</a>'
         * </code>
         *
         * @param string|null $html Chuỗi cần xử lý
         * @param string|null $allowableTags Các thẻ HTML cần được bỏ qua
         * @return string
         */
        public static function stripTags(?string $html, ?string $allowableTags = null): string
        {
            $normalizeTags = '';
            $allowableAttributes = [];

            if (!empty($allowableTags) && preg_match_all("/\<([_a-z0-9-]+)([^>]*)\>/is", $allowableTags, $tags)) {
                $normalizeTags = '<' . implode('><', array_map('strtolower', $tags[1])) . '>';
                $attributes = array_map('trim', $tags[2]);
                foreach ($attributes as $key => $val) {
                    $allowableAttributes[strtolower($tags[1][$key])] =
                        array_map('strtolower', array_keys(self::parseAttrs($val)));
                }
            }

            $html = strip_tags($html, $normalizeTags);
            return preg_replace_callback(
                "/<([_a-z0-9-]+)(\s+[^>]+)?>/is",
                function ($matches) use ($allowableAttributes) {
                    if (!isset($matches[2])) {
                        return $matches[0];
                    }

                    $str = trim($matches[2]);

                    if (empty($str)) {
                        return $matches[0];
                    }

                    $attrs = self::parseAttrs($str);
                    $parsedAttrs = [];
                    $tag = strtolower($matches[1]);

                    foreach ($attrs as $key => $val) {
                        if (in_array($key, $allowableAttributes[$tag])) {
                            $parsedAttrs[] = " {$key}" . (empty($val) ? '' : "={$val}");
                        }
                    }

                    return '<' . $tag . implode('', $parsedAttrs) . '>';
                },
                $html
            );
        }

        /**
         * Lọc chuỗi để tìm kiếm
         *
         * @access public
         *
         * @param string|null $query chuỗi tìm kiếm
         *
         * @return string
         */
        public static function filterSearchQuery(?string $query): string
        {
            return isset($query) ? str_replace('-', ' ', self::slugName($query) ?? '') : '';
        }

        /**
         * Tạo chữ viết tắt
         *
         * @access public
         *
         * @param string|null $str Chuỗi cần tạo chữ viết tắt
         * @param string|null $default Viết tắt mặc định
         * @param integer $maxLength Độ dài tối đa của chữ viết tắt
         *
         * @return string
         */
        public static function slugName(?string $str, ?string $default = null, int $maxLength = 128): ?string
        {
            $str = trim($str ?? '');

            if (!strlen($str)) {
                return $default;
            }

            mb_regex_encoding('UTF-8');
            mb_ereg_search_init($str, "[\w" . preg_quote('_-') . "]+");
            $result = mb_ereg_search();
            $return = '';

            if ($result) {
                $regs = mb_ereg_search_getregs();
                $pos = 0;
                do {
                    $return .= ($pos > 0 ? '-' : '') . $regs[0];
                    $pos++;
                } while ($regs = mb_ereg_search_regs());
            }

            $str = trim($return, '-_');
            $str = !strlen($str) ? $default : $str;
            return substr($str, 0, $maxLength);
        }

        /**
         * Thay thế các chuỗi không hợp lệ trong url
         *
         * @param string $url URL cần được lọc
         *
         * @return string
         */
        public static function safeUrl($url)
        {
            //~ Lọc XSS cho vị trí, vì tính đặc thù của nó nên không thể sử dụng chức năng RemoveXSS
            //~ fix issue 66
            $params = parse_url(str_replace(["\r", "\n", "\t", ' '], '', $url));

            /** Cấm nhảy giao thức bất hợp pháp */
            if (isset($params['scheme'])) {
                if (!in_array($params['scheme'], ['http', 'https'])) {
                    return '/';
                }
            }

            $params = array_map(function ($string) {
                $string = str_replace(['%0d', '%0a'], '', strip_tags($string));
                return preg_replace([
                    "/\(\s*(\"|')/i",           // Bắt đầu chức năng
                    "/(\"|')\s*\)/i",           // kết thúc chức năng
                ], '', $string);
            }, $params);

            return self::buildUrl($params);
        }

        /**
         * Tập hợp lại url dựa trên kết quả của pars_url
         *
         * @param array $params Tham số được phân tích cú pháp
         *
         * @return string
         */
        public static function buildUrl(array $params): string
        {
            return (isset($params['scheme']) ? $params['scheme'] . '://' : null)
                . (isset($params['user']) ? $params['user']
                    . (isset($params['pass']) ? ':' . $params['pass'] : null) . '@' : null)
                . ($params['host'] ?? null)
                . (isset($params['port']) ? ':' . $params['port'] : null)
                . ($params['path'] ?? null)
                . (isset($params['query']) ? '?' . $params['query'] : null)
                . (isset($params['fragment']) ? '#' . $params['fragment'] : null);
        }

        /**
         * Chức năng lọc để xử lý các cuộc tấn công XSS chéo trang
         *
         * @param string|null $val Chuỗi cần xử lý
         * @return string
         */
        public static function removeXSS(?string $val): string
        {
            // remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
            // this prevents some character re-spacing such as <java\0script>
            // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
            $val = preg_replace('/([\x00-\x08]|[\x0b-\x0c]|[\x0e-\x19])/', '', $val);

            // straight replacements, the user should never need these since they're normal characters
            // this prevents like <IMG SRC=&#X40&#X61&#X76&#X61&#X73&#X63&#X72&#X69&#X70&#X74&#X3A&#X61&#X6C&#X65&#X72&#X74&#X28&#X27&#X58&#X53&#X53&#X27&#X29>
            $search = 'abcdefghijklmnopqrstuvwxyz';
            $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $search .= '1234567890!@#$%^&*()';
            $search .= '~`";:?+/={}[]-_|\'\\';

            for ($i = 0; $i < strlen($search); $i++) {
                // ;? matches the ;, which is optional
                // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars

                // &#x0040 @ search for the hex values
                $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val);
                // &#00064 @ 0{0,7} matches '0' zero to seven times
                $val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val); // with a ;
            }

            // now the only remaining whitespace attacks are \t, \n, and \r
            $ra1 = ['javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script',
                    'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base'];
            $ra2 = [
                'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy',
                'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint',
                'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick',
                'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged',
                'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave',
                'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish',
                'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup',
                'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter',
                'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel',
                'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange',
                'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete',
                'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop',
                'onsubmit', 'onunload'
            ];
            $ra = array_merge($ra1, $ra2);

            $found = true; // keep replacing as long as the previous round replaced something
            while ($found == true) {
                $val_before = $val;
                for ($i = 0; $i < sizeof($ra); $i++) {
                    $pattern = '/';
                    for ($j = 0; $j < strlen($ra[$i]); $j++) {
                        if ($j > 0) {
                            $pattern .= '(';
                            $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                            $pattern .= '|';
                            $pattern .= '|(&#0{0,8}([9|10|13]);)';
                            $pattern .= ')*';
                        }
                        $pattern .= $ra[$i][$j];
                    }
                    $pattern .= '/i';
                    $replacement = substr($ra[$i], 0, 2) . '<x>' . substr($ra[$i], 2); // add in <> to nerf the tag
                    $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags

                    if ($val_before == $val) {
                        // no replacements were made, so exit the loop
                        $found = false;
                    }
                }
            }

            return $val;
        }

        /**
         * chức năng cắt chuỗi rộng
         *
         * @param string $str Chuỗi cần bị chặn
         * @param integer $start Bắt đầu vị trí đánh chặn
         * @param integer $length Chiều dài cần cắt
         * @param string $trim định danh cắt ngắn sau khi cắt ngắn
         *
         * @return string
         */
        public static function subStr(string $str, int $start, int $length, string $trim = "..."): string
        {
            if (!strlen($str)) {
                return '';
            }

            $iLength = self::strLen($str) - $start;
            $tLength = $length < $iLength ? ($length - self::strLen($trim)) : $length;
            $str = mb_substr($str, $start, $tLength, 'UTF-8');

            return $length < $iLength ? ($str . $trim) : $str;
        }

        /**
         * Nhận chức năng chiều dài chuỗi rộng
         *
         * @param string $str Cần lấy độ dài của chuỗi
         * @return integer
         */
        public static function strLen(string $str): int
        {
            return mb_strlen($str, 'UTF-8');
        }

        /**
         * Xác định xem giá trị băm có bằng nhau không
         *
         * @access public
         *
         * @param string|null $from chuỗi nguồn
         * @param string|null $to chuỗi mục tiêu
         *
         * @return boolean
         */
        public static function hashValidate(?string $from, ?string $to): bool
        {
            if (!isset($from) || !isset($to)) {
                return false;
            }

            if ('$T$' == substr($to, 0, 3)) {
                $salt = substr($to, 3, 9);
                return self::hash($from, $salt) === $to;
            } else {
                return md5($from) === $to;
            }
        }

        /**
         * Mã hóa băm của chuỗi
         *
         * @access public
         *
         * @param string|null $string Chuỗi cần được băm
         * @param string|null $salt tranh giành
         *
         * @return string
         */
        public static function hash(?string $string, ?string $salt = null): string
        {
            if (!isset($string)) {
                return '';
            }

            /** Tạo chuỗi ngẫu nhiên */
            $salt = empty($salt) ? self::randString(9) : $salt;
            $length = strlen($string);

            if ($length == 0) {
                return '';
            }

            $hash = '';
            $last = ord($string[$length - 1]);
            $pos = 0;

            /** Xác định độ dài mã xáo trộn */
            if (strlen($salt) != 9) {
                /** Nếu không phải 9 thì quay lại trực tiếp */
                return '';
            }

            while ($pos < $length) {
                $asc = ord($string[$pos]);
                $last = ($last * ord($salt[($last % $asc) % 9]) + $asc) % 95 + 32;
                $hash .= chr($last);
                $pos++;
            }

            return '$T$' . $salt . md5($hash);
        }

        /**
         * Tạo chuỗi ngẫu nhiên
         *
         * @access public
         *
         * @param integer $length độ dài chuỗi
         * @param boolean $specialChars Có ký tự đặc biệt nào không?
         *
         * @return string
         */
        public static function randString(int $length, bool $specialChars = false): string
        {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            if ($specialChars) {
                $chars .= '!@#$%^&*()';
            }

            $result = '';
            $max = strlen($chars) - 1;
            for ($i = 0; $i < $length; $i++) {
                $result .= $chars[rand(0, $max)];
            }
            return $result;
        }

        /**
         * Tạo mã thông báo sẽ hết hạn
         *
         * @param $secret
         * @return string
         */
        public static function timeToken($secret): string
        {
            return sha1($secret . '&' . time());
        }

        /**
         * Xác minh mã thông báo trong phạm vi thời gian
         *
         * @param $token
         * @param $secret
         * @param int $timeout
         * @return bool
         */
        public static function timeTokenValidate($token, $secret, int $timeout = 5): bool
        {
            $now = time();
            $from = $now - $timeout;

            for ($i = $now; $i >= $from; $i--) {
                if (sha1($secret . '&' . $i) == $token) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Nhận địa chỉ avatar gravatar
         *
         * @param string|null $mail
         * @param int $size
         * @param string|null $rating
         * @param string|null $default
         * @param bool $isSecure
         *
         * @return string
         */
        public static function gravatarUrl(
            ?string $mail,
            int $size,
            ?string $rating = null,
            ?string $default = null,
            bool $isSecure = true
        ): string {
            if (defined('__TYPECHO_GRAVATAR_PREFIX__')) {
                $url = __TYPECHO_GRAVATAR_PREFIX__;
            } else {
                $url = $isSecure ? 'https://secure.gravatar.com' : 'http://www.gravatar.com';
                $url .= '/avatar/';
            }

            if (!empty($mail)) {
                $url .= md5(strtolower(trim($mail)));
            }

            $url .= '?s=' . $size;

            if (isset($rating)) {
                $url .= '&amp;r=' . $rating;
            }

            if (isset($default)) {
                $url .= '&amp;d=' . $default;
            }

            return $url;
        }

        /**
         * Thêm thiết kế xáo trộn vào bài tập JavaScript
         *
         * @param string $value
         *
         * @return string
         */
        public static function shuffleScriptVar(string $value): string
        {
            $length = strlen($value);
            $max = 3;
            $offset = 0;
            $result = [];
            $cut = [];

            while ($length > 0) {
                $len = rand(0, min($max, $length));
                $rand = "'" . self::randString(rand(1, $max)) . "'";

                if ($len > 0) {
                    $val = "'" . substr($value, $offset, $len) . "'";
                    $result[] = rand(0, 1) ? "//{$rand}\n{$val}" : "{$val}//{$rand}\n";
                } else {
                    if (rand(0, 1)) {
                        $result[] = rand(0, 1) ? "''///*{$rand}*/{$rand}\n" : "/* {$rand}//{$rand} */''";
                    } else {
                        $result[] = rand(0, 1) ? "//{$rand}\n{$rand}" : "{$rand}//{$rand}\n";
                        $cut[] = [$offset, strlen($rand) - 2 + $offset];
                    }
                }

                $offset += $len;
                $length -= $len;
            }

            $name = '_' . self::randString(rand(3, 7));
            $cutName = '_' . self::randString(rand(3, 7));
            $var = implode('+', $result);
            $cutVar = json_encode($cut);
            return "(function () {
    var {$name} = {$var}, {$cutName} = {$cutVar};
    
    for (var i = 0; i < {$cutName}.length; i ++) {
        {$name} = {$name}.substring(0, {$cutName}[i][0]) + {$name}.substring({$cutName}[i][1]);
    }

    return {$name};
})();";
        }

        /**
         * Tạo bộ đệm tập tin sao lưu
         *
         * @param string $type
         * @param string $header
         * @param string $body
         *
         * @return string
         */
        public static function buildBackupBuffer(string $type, string $header, string $body): string
        {
            $buffer = '';

            $buffer .= pack('vvV', $type, strlen($header), strlen($body));
            $buffer .= $header . $body;
            $buffer .= md5($buffer);

            return $buffer;
        }

        /**
         * Trích xuất từ ​​tập tin sao lưu
         *
         * @param resource $fp
         * @param int|null $offset
         * @param string $version
         * @return array|bool
         */
        public static function extractBackupBuffer($fp, ?int &$offset, string $version)
        {
            $realMetaLen = $version == 'FILE' ? 6 : 8;

            $meta = fread($fp, $realMetaLen);
            $offset += $realMetaLen;
            $metaLen = strlen($meta);

            if (false === $meta || $metaLen != $realMetaLen) {
                return false;
            }

            [$type, $headerLen, $bodyLen]
                = array_values(unpack($version == 'FILE' ? 'v3' : 'v1type/v1headerLen/V1bodyLen', $meta));

            $header = @fread($fp, $headerLen);
            $offset += $headerLen;

            if (false === $header || strlen($header) != $headerLen) {
                return false;
            }

            if ('FILE' == $version) {
                $bodyLen = array_reduce(json_decode($header, true), function ($carry, $len) {
                    return null === $len ? $carry : $carry + $len;
                }, 0);
            }

            $body = @fread($fp, $bodyLen);
            $offset += $bodyLen;

            if (false === $body || strlen($body) != $bodyLen) {
                return false;
            }

            $md5 = @fread($fp, 32);
            $offset += 32;

            if (false === $md5 || $md5 != md5($meta . $header . $body)) {
                return false;
            }

            return [$type, $header, $body];
        }

        /**
         * Kiểm tra xem đó có phải là tên máy chủ an toàn không
         *
         * @param string $host
         * @return bool
         */
        public static function checkSafeHost(string $host): bool
        {
            if ('localhost' == $host) {
                return false;
            }

            $address = gethostbyname($host);
            $inet = inet_pton($address);

            if (false === $inet) {
                // 有可能是ipv6的地址
                $records = dns_get_record($host, DNS_AAAA);

                if (empty($records)) {
                    return false;
                }

                $address = $records[0]['ipv6'];
                $inet = inet_pton($address);
            }

            return filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        /**
         * @return bool
         * @deprecated after 1.2.0
         */
        public static function isAppEngine(): bool
        {
            return false;
        }

        /**
         * Nhận hình ảnh
         *
         * @access public
         *
         * @param string $fileName tên tập tin
         *
         * @return string
         */
        public static function mimeContentType(string $fileName): string
        {
            // Thay đổi phán quyết song song
            if (function_exists('mime_content_type')) {
                return mime_content_type($fileName);
            }

            if (function_exists('finfo_open')) {
                $fInfo = @finfo_open(FILEINFO_MIME_TYPE);

                if (false !== $fInfo) {
                    $mimeType = finfo_file($fInfo, $fileName);
                    finfo_close($fInfo);
                    return $mimeType;
                }
            }

            $mimeTypes = [
                'ez'       => 'application/andrew-inset',
                'csm'      => 'application/cu-seeme',
                'cu'       => 'application/cu-seeme',
                'tsp'      => 'application/dsptype',
                'spl'      => 'application/x-futuresplash',
                'hta'      => 'application/hta',
                'cpt'      => 'image/x-corelphotopaint',
                'hqx'      => 'application/mac-binhex40',
                'nb'       => 'application/mathematica',
                'mdb'      => 'application/msaccess',
                'doc'      => 'application/msword',
                'dot'      => 'application/msword',
                'bin'      => 'application/octet-stream',
                'oda'      => 'application/oda',
                'ogg'      => 'application/ogg',
                'oga'      => 'audio/ogg',
                'ogv'      => 'video/ogg',
                'prf'      => 'application/pics-rules',
                'key'      => 'application/pgp-keys',
                'pdf'      => 'application/pdf',
                'pgp'      => 'application/pgp-signature',
                'ps'       => 'application/postscript',
                'ai'       => 'application/postscript',
                'eps'      => 'application/postscript',
                'rss'      => 'application/rss+xml',
                'rtf'      => 'text/rtf',
                'smi'      => 'application/smil',
                'smil'     => 'application/smil',
                'wp5'      => 'application/wordperfect5.1',
                'xht'      => 'application/xhtml+xml',
                'xhtml'    => 'application/xhtml+xml',
                'zip'      => 'application/zip',
                'cdy'      => 'application/vnd.cinderella',
                'mif'      => 'application/x-mif',
                'xls'      => 'application/vnd.ms-excel',
                'xlb'      => 'application/vnd.ms-excel',
                'cat'      => 'application/vnd.ms-pki.seccat',
                'stl'      => 'application/vnd.ms-pki.stl',
                'ppt'      => 'application/vnd.ms-powerpoint',
                'pps'      => 'application/vnd.ms-powerpoint',
                'pot'      => 'application/vnd.ms-powerpoint',
                'sdc'      => 'application/vnd.stardivision.calc',
                'sda'      => 'application/vnd.stardivision.draw',
                'sdd'      => 'application/vnd.stardivision.impress',
                'sdp'      => 'application/vnd.stardivision.impress',
                'smf'      => 'application/vnd.stardivision.math',
                'sdw'      => 'application/vnd.stardivision.writer',
                'vor'      => 'application/vnd.stardivision.writer',
                'sgl'      => 'application/vnd.stardivision.writer-global',
                'sxc'      => 'application/vnd.sun.xml.calc',
                'stc'      => 'application/vnd.sun.xml.calc.template',
                'sxd'      => 'application/vnd.sun.xml.draw',
                'std'      => 'application/vnd.sun.xml.draw.template',
                'sxi'      => 'application/vnd.sun.xml.impress',
                'sti'      => 'application/vnd.sun.xml.impress.template',
                'sxm'      => 'application/vnd.sun.xml.math',
                'sxw'      => 'application/vnd.sun.xml.writer',
                'sxg'      => 'application/vnd.sun.xml.writer.global',
                'stw'      => 'application/vnd.sun.xml.writer.template',
                'sis'      => 'application/vnd.symbian.install',
                'wbxml'    => 'application/vnd.wap.wbxml',
                'wmlc'     => 'application/vnd.wap.wmlc',
                'wmlsc'    => 'application/vnd.wap.wmlscriptc',
                'wk'       => 'application/x-123',
                'dmg'      => 'application/x-apple-diskimage',
                'bcpio'    => 'application/x-bcpio',
                'torrent'  => 'application/x-bittorrent',
                'cdf'      => 'application/x-cdf',
                'vcd'      => 'application/x-cdlink',
                'pgn'      => 'application/x-chess-pgn',
                'cpio'     => 'application/x-cpio',
                'csh'      => 'text/x-csh',
                'deb'      => 'application/x-debian-package',
                'dcr'      => 'application/x-director',
                'dir'      => 'application/x-director',
                'dxr'      => 'application/x-director',
                'wad'      => 'application/x-doom',
                'dms'      => 'application/x-dms',
                'dvi'      => 'application/x-dvi',
                'pfa'      => 'application/x-font',
                'pfb'      => 'application/x-font',
                'gsf'      => 'application/x-font',
                'pcf'      => 'application/x-font',
                'pcf.Z'    => 'application/x-font',
                'gnumeric' => 'application/x-gnumeric',
                'sgf'      => 'application/x-go-sgf',
                'gcf'      => 'application/x-graphing-calculator',
                'gtar'     => 'application/x-gtar',
                'tgz'      => 'application/x-gtar',
                'taz'      => 'application/x-gtar',
                'gz'       => 'application/x-gtar',
                'hdf'      => 'application/x-hdf',
                'phtml'    => 'application/x-httpd-php',
                'pht'      => 'application/x-httpd-php',
                'php'      => 'application/x-httpd-php',
                'phps'     => 'application/x-httpd-php-source',
                'php3'     => 'application/x-httpd-php3',
                'php3p'    => 'application/x-httpd-php3-preprocessed',
                'php4'     => 'application/x-httpd-php4',
                'ica'      => 'application/x-ica',
                'ins'      => 'application/x-internet-signup',
                'isp'      => 'application/x-internet-signup',
                'iii'      => 'application/x-iphone',
                'jar'      => 'application/x-java-archive',
                'jnlp'     => 'application/x-java-jnlp-file',
                'ser'      => 'application/x-java-serialized-object',
                'class'    => 'application/x-java-vm',
                'js'       => 'application/x-javascript',
                'chrt'     => 'application/x-kchart',
                'kil'      => 'application/x-killustrator',
                'kpr'      => 'application/x-kpresenter',
                'kpt'      => 'application/x-kpresenter',
                'skp'      => 'application/x-koan',
                'skd'      => 'application/x-koan',
                'skt'      => 'application/x-koan',
                'skm'      => 'application/x-koan',
                'ksp'      => 'application/x-kspread',
                'kwd'      => 'application/x-kword',
                'kwt'      => 'application/x-kword',
                'latex'    => 'application/x-latex',
                'lha'      => 'application/x-lha',
                'lzh'      => 'application/x-lzh',
                'lzx'      => 'application/x-lzx',
                'frm'      => 'application/x-maker',
                'maker'    => 'application/x-maker',
                'frame'    => 'application/x-maker',
                'fm'       => 'application/x-maker',
                'fb'       => 'application/x-maker',
                'book'     => 'application/x-maker',
                'fbdoc'    => 'application/x-maker',
                'wmz'      => 'application/x-ms-wmz',
                'wmd'      => 'application/x-ms-wmd',
                'com'      => 'application/x-msdos-program',
                'exe'      => 'application/x-msdos-program',
                'bat'      => 'application/x-msdos-program',
                'dll'      => 'application/x-msdos-program',
                'msi'      => 'application/x-msi',
                'nc'       => 'application/x-netcdf',
                'pac'      => 'application/x-ns-proxy-autoconfig',
                'nwc'      => 'application/x-nwc',
                'o'        => 'application/x-object',
                'oza'      => 'application/x-oz-application',
                'pl'       => 'application/x-perl',
                'pm'       => 'application/x-perl',
                'p7r'      => 'application/x-pkcs7-certreqresp',
                'crl'      => 'application/x-pkcs7-crl',
                'qtl'      => 'application/x-quicktimeplayer',
                'rpm'      => 'audio/x-pn-realaudio-plugin',
                'shar'     => 'application/x-shar',
                'swf'      => 'application/x-shockwave-flash',
                'swfl'     => 'application/x-shockwave-flash',
                'sh'       => 'text/x-sh',
                'sit'      => 'application/x-stuffit',
                'sv4cpio'  => 'application/x-sv4cpio',
                'sv4crc'   => 'application/x-sv4crc',
                'tar'      => 'application/x-tar',
                'tcl'      => 'text/x-tcl',
                'tex'      => 'text/x-tex',
                'gf'       => 'application/x-tex-gf',
                'pk'       => 'application/x-tex-pk',
                'texinfo'  => 'application/x-texinfo',
                'texi'     => 'application/x-texinfo',
                '~'        => 'application/x-trash',
                '%'        => 'application/x-trash',
                'bak'      => 'application/x-trash',
                'old'      => 'application/x-trash',
                'sik'      => 'application/x-trash',
                't'        => 'application/x-troff',
                'tr'       => 'application/x-troff',
                'roff'     => 'application/x-troff',
                'man'      => 'application/x-troff-man',
                'me'       => 'application/x-troff-me',
                'ms'       => 'application/x-troff-ms',
                'ustar'    => 'application/x-ustar',
                'src'      => 'application/x-wais-source',
                'wz'       => 'application/x-wingz',
                'crt'      => 'application/x-x509-ca-cert',
                'fig'      => 'application/x-xfig',
                'au'       => 'audio/basic',
                'snd'      => 'audio/basic',
                'mid'      => 'audio/midi',
                'midi'     => 'audio/midi',
                'kar'      => 'audio/midi',
                'mpga'     => 'audio/mpeg',
                'mpega'    => 'audio/mpeg',
                'mp2'      => 'audio/mpeg',
                'mp3'      => 'audio/mpeg',
                'mp4'      => 'video/mp4',
                'm3u'      => 'audio/x-mpegurl',
                'sid'      => 'audio/prs.sid',
                'aif'      => 'audio/x-aiff',
                'aiff'     => 'audio/x-aiff',
                'aifc'     => 'audio/x-aiff',
                'gsm'      => 'audio/x-gsm',
                'wma'      => 'audio/x-ms-wma',
                'wax'      => 'audio/x-ms-wax',
                'ra'       => 'audio/x-realaudio',
                'rm'       => 'audio/x-pn-realaudio',
                'ram'      => 'audio/x-pn-realaudio',
                'pls'      => 'audio/x-scpls',
                'sd2'      => 'audio/x-sd2',
                'wav'      => 'audio/x-wav',
                'pdb'      => 'chemical/x-pdb',
                'xyz'      => 'chemical/x-xyz',
                'bmp'      => 'image/x-ms-bmp',
                'gif'      => 'image/gif',
                'ief'      => 'image/ief',
                'jpeg'     => 'image/jpeg',
                'jpg'      => 'image/jpeg',
                'jpe'      => 'image/jpeg',
                'pcx'      => 'image/pcx',
                'png'      => 'image/png',
                'svg'      => 'image/svg+xml',
                'svgz'     => 'image/svg+xml',
                'tiff'     => 'image/tiff',
                'tif'      => 'image/tiff',
                'wbmp'     => 'image/vnd.wap.wbmp',
                'ras'      => 'image/x-cmu-raster',
                'cdr'      => 'image/x-coreldraw',
                'pat'      => 'image/x-coreldrawpattern',
                'cdt'      => 'image/x-coreldrawtemplate',
                'djvu'     => 'image/x-djvu',
                'djv'      => 'image/x-djvu',
                'ico'      => 'image/x-icon',
                'art'      => 'image/x-jg',
                'jng'      => 'image/x-jng',
                'psd'      => 'image/x-photoshop',
                'pnm'      => 'image/x-portable-anymap',
                'pbm'      => 'image/x-portable-bitmap',
                'pgm'      => 'image/x-portable-graymap',
                'ppm'      => 'image/x-portable-pixmap',
                'rgb'      => 'image/x-rgb',
                'xbm'      => 'image/x-xbitmap',
                'xpm'      => 'image/x-xpixmap',
                'xwd'      => 'image/x-xwindowdump',
                'igs'      => 'model/iges',
                'iges'     => 'model/iges',
                'msh'      => 'model/mesh',
                'mesh'     => 'model/mesh',
                'silo'     => 'model/mesh',
                'wrl'      => 'x-world/x-vrml',
                'vrml'     => 'x-world/x-vrml',
                'csv'      => 'text/comma-separated-values',
                'css'      => 'text/css',
                '323'      => 'text/h323',
                'htm'      => 'text/html',
                'html'     => 'text/html',
                'uls'      => 'text/iuls',
                'mml'      => 'text/mathml',
                'asc'      => 'text/plain',
                'txt'      => 'text/plain',
                'text'     => 'text/plain',
                'diff'     => 'text/plain',
                'rtx'      => 'text/richtext',
                'sct'      => 'text/scriptlet',
                'wsc'      => 'text/scriptlet',
                'tm'       => 'text/texmacs',
                'ts'       => 'text/texmacs',
                'tsv'      => 'text/tab-separated-values',
                'jad'      => 'text/vnd.sun.j2me.app-descriptor',
                'wml'      => 'text/vnd.wap.wml',
                'wmls'     => 'text/vnd.wap.wmlscript',
                'xml'      => 'text/xml',
                'xsl'      => 'text/xml',
                'h++'      => 'text/x-c++hdr',
                'hpp'      => 'text/x-c++hdr',
                'hxx'      => 'text/x-c++hdr',
                'hh'       => 'text/x-c++hdr',
                'c++'      => 'text/x-c++src',
                'cpp'      => 'text/x-c++src',
                'cxx'      => 'text/x-c++src',
                'cc'       => 'text/x-c++src',
                'h'        => 'text/x-chdr',
                'c'        => 'text/x-csrc',
                'java'     => 'text/x-java',
                'moc'      => 'text/x-moc',
                'p'        => 'text/x-pascal',
                'pas'      => 'text/x-pascal',
                '***'      => 'text/x-pcs-***',
                'shtml'    => 'text/x-server-parsed-html',
                'etx'      => 'text/x-setext',
                'tk'       => 'text/x-tcl',
                'ltx'      => 'text/x-tex',
                'sty'      => 'text/x-tex',
                'cls'      => 'text/x-tex',
                'vcs'      => 'text/x-vcalendar',
                'vcf'      => 'text/x-vcard',
                'dl'       => 'video/dl',
                'fli'      => 'video/fli',
                'gl'       => 'video/gl',
                'mpeg'     => 'video/mpeg',
                'mpg'      => 'video/mpeg',
                'mpe'      => 'video/mpeg',
                'qt'       => 'video/quicktime',
                'mov'      => 'video/quicktime',
                'mxu'      => 'video/vnd.mpegurl',
                'dif'      => 'video/x-dv',
                'dv'       => 'video/x-dv',
                'lsf'      => 'video/x-la-asf',
                'lsx'      => 'video/x-la-asf',
                'mng'      => 'video/x-mng',
                'asf'      => 'video/x-ms-asf',
                'asx'      => 'video/x-ms-asf',
                'wm'       => 'video/x-ms-wm',
                'wmv'      => 'video/x-ms-wmv',
                'wmx'      => 'video/x-ms-wmx',
                'wvx'      => 'video/x-ms-wvx',
                'avi'      => 'video/x-msvideo',
                'movie'    => 'video/x-sgi-movie',
                'ice'      => 'x-conference/x-cooltalk',
                'vrm'      => 'x-world/x-vrml',
                'rar'      => 'application/x-rar-compressed',
                'cab'      => 'application/vnd.ms-cab-compressed',
                'webp'     => 'image/webp'
            ];

            $part = explode('.', $fileName);
            $size = count($part);

            if ($size > 1) {
                $ext = $part[$size - 1];
                if (isset($mimeTypes[$ext])) {
                    return $mimeTypes[$ext];
                }
            }

            return 'application/octet-stream';
        }

        /**
         * Tìm biểu tượng mime phù hợp
         *
         * @access public
         *
         * @param string $mime mime kiểu
         *
         * @return string
         */
        public static function mimeIconType(string $mime): string
        {
            $parts = explode('/', $mime);

            if (count($parts) < 2) {
                return 'unknown';
            }

            [$type, $stream] = $parts;

            if (in_array($type, ['image', 'video', 'audio', 'text', 'application'])) {
                switch (true) {
                    case in_array($stream, ['msword', 'msaccess', 'ms-powerpoint', 'ms-powerpoint']):
                    case 0 === strpos($stream, 'vnd.'):
                        return 'office';
                    case false !== strpos($stream, 'html')
                        || false !== strpos($stream, 'xml')
                        || false !== strpos($stream, 'wml'):
                        return 'html';
                    case false !== strpos($stream, 'compressed')
                        || false !== strpos($stream, 'zip')
                        || in_array($stream, ['application/x-gtar', 'application/x-tar']):
                        return 'archive';
                    case 'text' == $type && 0 === strpos($stream, 'x-'):
                        return 'script';
                    default:
                        return $type;
                }
            } else {
                return 'unknown';
            }
        }

        /**
         * Phân tích thuộc tính
         *
         * @param string $attrs chuỗi được gán
         * @return array
         */
        private static function parseAttrs(string $attrs): array
        {
            $attrs = trim($attrs);
            $len = strlen($attrs);
            $pos = -1;
            $result = [];
            $quote = '';
            $key = '';
            $value = '';

            for ($i = 0; $i < $len; $i++) {
                if ('=' != $attrs[$i] && !ctype_space($attrs[$i]) && -1 == $pos) {
                    $key .= $attrs[$i];

                    /** cái cuối cùng */
                    if ($i == $len - 1) {
                        if ('' != ($key = trim($key))) {
                            $result[$key] = '';
                            $key = '';
                            $value = '';
                        }
                    }

                } elseif (ctype_space($attrs[$i]) && -1 == $pos) {
                    $pos = -2;
                } elseif ('=' == $attrs[$i] && 0 > $pos) {
                    $pos = 0;
                } elseif (('"' == $attrs[$i] || "'" == $attrs[$i]) && 0 == $pos) {
                    $quote = $attrs[$i];
                    $value .= $attrs[$i];
                    $pos = 1;
                } elseif ($quote != $attrs[$i] && 1 == $pos) {
                    $value .= $attrs[$i];
                } elseif ($quote == $attrs[$i] && 1 == $pos) {
                    $pos = -1;
                    $value .= $attrs[$i];
                    $result[trim($key)] = $value;
                    $key = '';
                    $value = '';
                } elseif ('=' != $attrs[$i] && !ctype_space($attrs[$i]) && -2 == $pos) {
                    if ('' != ($key = trim($key))) {
                        $result[$key] = '';
                    }

                    $key = '';
                    $value = '';
                    $pos = -1;
                    $key .= $attrs[$i];
                }
            }

            return $result;
        }
    }
}
