<?php

namespace Mailer;

use Auxclass\Translation\AutomaticTranslation;

/**
 * 此类只负责发送邮件，仅仅至此验证验证码，保存验证码再接口处
 * Class SendMail
 * @package Mailer
 *
 * @example
 * $mail = new \Mailer\SendMail(array(
 *      "host" => "smtp.163.com",
 *      "port" => 465,
 *      "userName" => "测试邮件发送者",
 *      "userAccount" => "emaple@163.com",
 *      "secret" => "secret",
 *      "language" => 1
 * ));
 * list($errNo, $errMsg) = $mail->send(array(
 *      "type" => "register",
 *      "data" => array(
 *          "code" => mt_rand(100000, 999999)
 *      ),
 *
 *      "addressees" => array(
 *          array("email" => "1634621150@qq.com", "nickName" => "用户1"),
 *          array("email" => "2585854334@qq.com", "nickName" => "用户2")
 *      ),
 *      "bccs" => array(
 *          array("email" => "caizhengjun@cqnu.edu.cn", "nickName" => "领导")
 *      ),
 *
 *      "attachments" => array(
 *          array(
 *              "url" => "./test.txt",
 *              "fileName" => "测试"
 *          )
 *      ),
 * ));
 * echo $errMsg;
 *
 */
class SendMail
{
    private static $language = 1; // 中文简体

    private static $serverHost = "";
    private static $serverPort = "";
    private static $userName = "";
    private static $userAccount = "";
    private static $userSecret = "";

    private static $emailResource;  //email

    /**
     * 模板
     * @var array
     */
    //注册html模板
    const RegisterHtmlTemplate = <<<EOF
<div>
    <div style="text-indent:2em">您正在使用邮箱注册小7手游，请使用以下验证码填入验证码框中。</div>
    <div style="text-indent:2em;font-size:1.2em;font-weight:bold;width:440px;text-align:center">{code}</div>
    <div style="text-indent:2em">如果这不是您本人所为，可能是有人误输了您的电子邮件地址。请勿将此验证码泄露给他人。</div>   
</div>
EOF;
    //找回密码html模板
    const FindPassHtmlTemplate = <<<EOF
<div>
    <div style="text-indent:2em">您正在使用邮箱找回密码，请使用以下验证码填入验证码框中。</div>
    <div style="text-indent:2em;font-size:1.2em;font-weight:bold;width:440px;text-align:center">{code}</div>
    <div style="text-indent:2em">如果这不是您本人所为，可能是有人误输了您的电子邮件地址。请勿将此验证码泄露给他人。</div>   
</div>
EOF;


    private static $contentArr = array(
        "register" => array(
            "isHtml" => true,                   // 是否为html
            "subject" => "小7手游注册验证码",                    // 主题
            "content" => self::RegisterHtmlTemplate,                    // 内容
            "altContent" => "小7手游:您正在使用邮箱注册小7手游，请使用以下验证码填入验证码框中。{code} 如果这不是您本人所为，可能是有人误输了您的电子邮件地址。请勿将此验证码泄露给他人。",
            "altContentNeedReplace" => true,    //altContent是否也需要进行字符串匹配替换
        ),
        "findpass" => array(
            "isHtml" => true,                   // 是否为html
            "subject" => "小7手游找回密码验证码",                    // 主题
            "content" => self::FindPassHtmlTemplate,                    // 内容
            "altContent" => "小7手游:您正在使用邮箱找回密码，请使用以下验证码填入验证码框中。{code} 如果这不是您本人所为，可能是有人误输了您的电子邮件地址。请勿将此验证码泄露给他人。",
            "altContentNeedReplace" => true,    //altContent是否也需要进行字符串匹配替换
        )
    );


    /**
     * SendMail constructor.
     * @param array $serverConfig
     * array(
     *      "host" => "host",
     *      "port" => "port",
     *      "userName" => "userName",
     *      "userAccount" => "userAccount",
     *      "userSecret" => "userSecret",
     *      "language" => "language"
     * )
     */
    public function __construct(array $serverConfig = array())
    {
        $this->_initServerSetting($serverConfig);
        self::$emailResource = new Mail();
    }

    /**
     * 格式化email，去除email前后空白字符
     * @param $email
     * @return array
     */
    public static function formatEmail($email)
    {
        $email = trim($email);
        if (self::isEmail($email)) {
            return array(0, $email);
        }
        return array(-1, '');
    }

    /**
     * 判定是否为email
     * @param $email
     * @return false|int
     */
    public static function isEmail($email)
    {
        return preg_match(EmailRegExp, $email);
    }

    /**
     * 检测code，是为了方便维护
     */
    public static function checkCode($email, $type, $code, $mid = -1)
    {
        $typeArr = array(
            "register" => 6,
            "findpass" => 2
        );
        if (array_key_exists($type, $typeArr)) {
            $operateType = $typeArr[$type];
        } else {
            return array(-1, "类型错误！");
        }


        list($errNo, $email) = SendMail::formatEmail($email);
        if ($errNo < 0) {
            ReturnAllNewAjaxData(-1, "邮箱不合法"); //邮箱不合法
        }

        $install_id = \Client::get_install_id();
        //是否被禁止操作
        list($isLock, $errMsg) = \Client::is_forbid_operate($install_id, $operateType, 2);
        if ($isLock == 1) {
            return array(-1, $errMsg);
        }

        if (empty($code)) {
            $errNo = \Client::accumulative_total_errors($install_id, $mid, $operateType, 2, 1);
            $errMsg = "验证码不能为空！";
            if ($errNo == -4) {
                $errMsg = "您输入错误信息次数过多，即将封禁您的邮箱验证";
            }
            return array(-1, $errMsg);
        }

        $cacheKey = md5($email . $type);
        $cacheVerifyCode = \Cache_tools::get_all_domain_cache($cacheKey);
        if (empty($cacheVerifyCode)) {
            return array(-1, "验证码已过期，请重新获取！");
        }

        if ($cacheVerifyCode != $code) {
            $errNo = \Client::accumulative_total_errors($install_id, $mid, $operateType, 2, 1);
            $errMsg = "验证码不正确！";
            if ($errNo == -4) {
                $errMsg = "您输入错误信息次数过多，即将封禁您的邮箱验证";
            }
            return array(-1, $errMsg);
        }
        //验证通过，清空错误次数缓存
        \Client::accumulative_total_errors($install_id, $mid, $operateType, 2, -1);
        return array(0, 'ok');
    }

    /**
     * 发送邮件
     * @param $params
     *  array(
     *      "type" => "需要发送的邮件类型", 根据此类型判定内容
     *      "addressees" => array(      //收件人
     *          array("email": "example@example.com", "nickName": "nickname"),
     *          ……
     *       ),
     *      "ccs" => array(             //抄送人
     *          array("email": "example@example.com", "nickName": "nickname"),
     *          ……
     *       ),
     *      "bccs" => array(            //密送人
     *          array("email": "example@example.com", "nickName": "nickname"),
     *          ……
     *       ),
     *      "attachments" => array(     //附件
     *          array("url": "file path", "fileName": "example"),
     *          ……
     *       )
     * )
     * @return array(errCode, errMsg)    errCode == -1 is FAIL  errCode == 0 is OK
     */
    public function send($params)
    {
        // 检测数据
        list($errNo, $errMsg) = $this->_checkData($params);
        if ($errNo < 0) {
            return array($errNo, $errMsg);
        }

        //处理收件人、抄送人、密送人等信息
        list($errNo, $emails) = $this->_format($params);
        if ($errNo < 0) {
            return array($errNo, $errMsg);
        }

        //看看模板内容是否需要正则替换
        list($content, $altContent) = $this->_replaceContent($params);


        $config = array(
            "host" => self::$serverHost,
            "port" => self::$serverPort,
            "userName" => AutomaticTranslation::translation(self::$userName, self::$language),
            "userAccount" => self::$userAccount,
            "secret" => self::$userSecret,

            "addressees" => $emails['addressees'],
            "ccs" => $emails['ccs'],
            "bccs" => $emails['bccs'],

            "attachments" => empty($params['attachments']) ? array() : $params['attachments'],

            "isHtml" => self::$contentArr[$params['type']]['isHtml'],
            "subject" => AutomaticTranslation::translation(self::$contentArr[$params['type']]['subject'], self::$language),
            "content" => AutomaticTranslation::translation($content, self::$language),
            "altContent" => AutomaticTranslation::translation($altContent, self::$language),
        );

        return self::$emailResource->handler($config);
    }

    /**
     * 获取后台默认配置
     */
    private function _getServerSetting()
    {
        $cacheKey = "emailServerSetting";
        $cache = \Cache_tools::get_cache($cacheKey);
        if (empty($cache)) {
            $ci = & get_instance();
            $ci->get_public_model('Systemset_model');
            $cache = $ci->Systemset_model->get_have_data_read_write(array(
                "systemset_type = '邮箱配置'"
            ), "systemset_ident as ident, systemset_value as value", true);
            $cache = \Arrays_tools::return_set_arr_key($cache, 'ident');
            \Cache_tools::set_cache($cacheKey, $cache);
        }

        self::$serverHost =  empty($cache['EmailServerHost']) ? '' : $cache['EmailServerHost']['value'];
        self::$serverPort =  empty($cache['EmailServerPort']) ? '' : $cache['EmailServerPort']['value'];
        self::$userAccount =  empty($cache['EmailUserAccount']) ? '' : $cache['EmailUserAccount']['value'];
        self::$userSecret =  empty($cache['EmailUserSecret']) ? '' : $cache['EmailUserSecret']['value'];
        self::$userName =  empty($cache['EmailUserNickName']) ? '' : $cache['EmailUserNickName']['value'];
    }

    /**
     * 初始化服务器配置
     * @param $serverConfigs
     */
    private function _initServerSetting($serverConfigs)
    {
        //加载默认配置
        $this->_getServerSetting();

        if (array_key_exists('host', $serverConfigs)) {
            self::$serverHost = $serverConfigs['host'];
        }

        if (array_key_exists('port', $serverConfigs)) {
            self::$serverPort = $serverConfigs['port'];
        }

        if (array_key_exists('userName', $serverConfigs)) {
            self::$userName = $serverConfigs['userName'];
        }

        if (array_key_exists('userAccount', $serverConfigs)) {
            self::$userAccount = $serverConfigs['userAccount'];
        }

        if (array_key_exists('secret', $serverConfigs)) {
            self::$userSecret = $serverConfigs['secret'];
        }

        if (array_key_exists('language', $serverConfigs)) {
            self::$language = $serverConfigs['language'];
        }
    }

    /**
     * 检测当前需要发送的邮件数据和邮件模板
     * @param $params
     * @return array
     */
    private function _checkData($params)
    {
        //先检测邮件服务器的相关配置
        if (empty(self::$serverHost) || empty(self::$serverPort) || empty(self::$userAccount) || empty(self::$userSecret)) {
            return array(-1, '邮件服务器相关配置不完全！');
        }

        //判定参数是否完全
        if (!array_key_exists('type', $params) || !(array_key_exists('type', $params) && array_key_exists($params['type'], self::$contentArr))) { //邮件类型是否存在
            return array(-1, '邮件模板不存在！');
        }

        //判断是否有收件人
        if (!array_key_exists('addressees', $params) || !(array_key_exists('addressees', $params) && is_array($params['addressees']))) {
            return array(-1, '收件人数据不合法！');
        }

        //判定模板内容是否完全
        if (empty(self::$contentArr[$params['type']]['subject']) || empty(self::$contentArr[$params['type']]['content']) || (self::$contentArr[$params['type']]['isHtml'] && empty(self::$contentArr[$params['type']]['altContent']))) {
            return array(-1, "邮件模板数据不完全！");
        }

        //判定数据格式是否正确

        //抄送
        if (array_key_exists('ccs', $params) && !is_array($params['ccs'])) {
            return array(-1, '抄送人数据不合法！');
        }

        //密送
        if (array_key_exists('bccs', $params) && !is_array($params['bccs'])) {
            return array(-1, '密送人数据不合法！');
        }

        //附件
        if (array_key_exists('attachments', $params) && !is_array($params['attachments'])) {
            return array(-1, '附件数据不合法！');
        }

        //替换数据
        if (array_key_exists('data', $params) && !is_array($params['data'])) {
            return array(-1, '数据格式不合法！');
        }

        return array(0, 'ok');
    }

    /**
     * 替换内容中的可变参数
     * @param $params
     * @return array(content, altContent)
     */
    private function _replaceContent($params)
    {
        $content = self::$contentArr[$params['type']]['content'];
        $altContent = self::$contentArr[$params['type']]['altContent'];
        if (array_key_exists('data', $params)) {
            $patternArr = array();
            foreach ($params['data'] as $key => $value) {
                $patternArr[] = "/\{{$key}\}/";
            }
            $valueArr = array_values($params['data']);

            $content = preg_replace($patternArr, $valueArr, $content);
            if (self::$contentArr[$params['type']]['altContentNeedReplace']) {
                $altContent = preg_replace($patternArr, $valueArr, $altContent);
            }
        }

        return array($content, $altContent);
    }

    /**
     * 统一格式化收件人、抄送人、密送人等邮件地址
     * @param $params
     * @return array
     */
    private function _format($params)
    {
        $wrongEmail = $addressees = $ccs = $bccs = array();
        //收件人
        if (array_key_exists('addressees', $params)) {
            foreach ($params['addressees'] as $key => $addressee) {
                list($errNo, $email) = self::formatEmail($addressee['email']);
                if ($errNo < 0) {
                    $wrongEmail[] = $addressee['email'];
                } else {
                    $addressees[] = array("email" => $email, "nickName" => empty($addressee['nickName']) ? '' : AutomaticTranslation::translation($addressee['nickName'], self::$language));
                }
            }
        }
        //抄送人
        if (array_key_exists('ccs', $params)) {
            foreach ($params['ccs'] as $key => $cc) {
                list($errNo, $email) = self::formatEmail($cc['email']);
                if ($errNo < 0) {
                    $wrongEmail[] = $cc['email'];
                } else {
                    $ccs[] = array("email" => $email, "nickName" => empty($cc['nickName']) ? '' : AutomaticTranslation::translation($cc['nickName'], self::$language));
                }
            }
        }
        //密送人
        if (array_key_exists('bccs', $params)) {
            foreach ($params['bccs'] as $key => $bcc) {
                list($errNo, $email) = self::formatEmail($bcc['email']);
                if ($errNo < 0) {
                    $wrongEmail[] = $bcc['email'];
                } else {
                    $bccs[] = array("email" => $email, "nickName" => empty($bcc['nickName']) ? '' : AutomaticTranslation::translation($bcc['nickName'], self::$language));
                }
            }
        }

        if (!empty($wrongEmail)) {
            return array(-1, implode(', ', $wrongEmail) . "邮箱格式有误！");
        }
        return array(0, array(
            "addressees" => $addressees,
            "ccs" => $ccs,
            "bccs" => $bccs,
        ));
    }
}
