<?php

namespace Mailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * 好好的composer,非要这样用
 */
require_once(APPPATH . "helpers" . DIRECTORY_SEPARATOR . "Class" . DIRECTORY_SEPARATOR . "Mailer" . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . 'PHPMailer.php');
require_once(APPPATH . "helpers" . DIRECTORY_SEPARATOR . "Class" . DIRECTORY_SEPARATOR . "Mailer" . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . 'SMTP.php');
require_once(APPPATH . "helpers" . DIRECTORY_SEPARATOR . "Class" . DIRECTORY_SEPARATOR . "Mailer" . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . 'Exception.php');


/**
 * Class Mail
 * @package Mailer
 *
 * @example
 * 1. simple example
 * $code = mt_rand(10000, 99999);
 * $content = <<<EOF
 * 这是一条测试邮件<br/>
 * <span style="color: red;font-size:36px;">{$code}</span><br />
 * 有效期为5分钟!
 * EOF;
 *
 * $mail = new \Mailer\Mail();
 * list($errNo, $errMsg) = $mail->handler(array(
 *      "host" => "smtp.163.com",
 *      "port" => 465,
 *      "userName" => "测试邮件发送者",
 *      "userAccount" => "example@163.com",
 *      "secret" => "secret",
 *
 *      "addressees" => array(
 *          array("email" => "1634621150@qq.com", "nickName" => "用户1"),
 *          array("email" => "2585854334@qq.com", "nickName" => "用户2")
 *      ),
 *      "bccs" => array(
 *          array("email" => "caizhengjun@cqnu.edu.cn", "nickName" => "领导")
 *      ),
 *
 *      "attachments" => array(),
 *
 *      "isHtml" => true,
 *      "subject" => "测试邮件发送功能",
 *      "content" => $content,
 *      "altContent" => "验证码为: {$code}"
 * ));
 * echo $errMsg;
 *
 * 2. 附件
 * $code = mt_rand(10000, 99999);
 * $content = <<<EOF
 * 这是一条测试邮件<br/>
 * <span style="color: red;font-size:36px;">{$code}</span><br />
 * 有效期为5分钟!
 * EOF;
 *
 * $mail = new \Mailer\Mail();
 * list($errNo, $errMsg) = $mail->handler(array(
 *      "host" => "smtp.163.com",
 *      "port" => 465,
 *      "userName" => "测试邮件发送者",
 *      "userAccount" => "example@163.com",
 *      "secret" => "secret",
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
 *
 *      "isHtml" => true,
 *      "subject" => "测试邮件发送功能",
 *      "content" => $content,
 *      "altContent" => "验证码为: {$code}"
 * ));
 * echo $errMsg;
 */
class Mail
{
    private $mail;

    /**
     * host;  //SMTP server
     * port;  //SMTP Prot
     * userAccount;  //SMTP userAccount  账号
     * userName;  //SMTP userName  发件人称呼
     * secret;  //SMTP server  密钥，不一定是密码
     *
     * addressees  //收件人
     * @var array
     *      array(
     *           array(
     *               "email" => "email address",
     *               "nickName" => "nick name"  //称呼
     *           )
     *           ……
     *      )
     *
     * ccs //抄送人
     * @var array
     *      array(
     *           array(
     *               "email" => "email address",
     *               "nickName" => "nick name"  //称呼
     *           )
     *           ……
     *      )
     *
     * bccs //密送人
     * @var array
     *      array(
     *           array(
     *               "email" => "email address",
     *               "nickName" => "nick name"  //称呼
     *           )
     *           ……
     *      )
     *
     * attachments //附件
     * @var array
     *      格式:
     *      array(
     *           array(
     *               "url" => "file url",
     *               "fileName" => "fileName"
     *           )
     *           ……
     *      )
     *
     * isHtml  //内容格式是否为html
     * subject  //主题
     * $content   //内容
     * $altContent  //当内容为html时，且客户端不支持Html格式时显示
     */
    private $configs = array(
        "host" => "",
        "port" => "",
        "userName" => "",
        "secret" => "",

        "addressees" => array(),
        "ccs" => array(),
        "bccs" => array(),

        "attachments" => array(),

        "isHtml" => false,
        "subject" => "",
        "content" => "",
        "altContent" => ""
    );

    /**
     * 必须的参数
     * @var array
     */
    private $mustParams = array(
        "host", "port", "userAccount", "secret", "addressees", "isHtml", "content", "subject"
    );

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
    }

    /**
     * 发送
     * @param $params
     * @return array
     */
    public function handler($params)
    {
        //初始化参数
        list($errNo, $errMsg) = $this->_initSetting($params);
        if ($errNo < 0) {
            return array($errNo, $errMsg);
        }

        //
        try {
            if ($this->mail->send()) {
                return array(0, "发送成功！");
            } else {
                throw new Exception('发送失败！');
            }
        } catch (Exception $e) {
            return array(-1, $e->getMessage());
        }
    }


    /**
     * @param array $params
     * @return array(errorNo, errorMsg)
     */
    private function _initSetting(array $params)
    {
        try {
            // 检测数据是否完全，必需参数是否传递完全
            $paramsKeys = array_keys($params);
            $keyDiffs = array_diff($this->mustParams, $paramsKeys);
            if (!empty($keyDiffs)) {
                throw new \Exception(implode(',', $paramsKeys) . " 以上参数为必需参数！");
            }
            //设置参数
            $this->configs = array_merge($this->configs, $params);

            //设置mail参数
            $this->mail->CharSet = "UTF-8";                                 //设定邮件编码
            $this->mail->SMTPDebug = SMTP::DEBUG_OFF;                   // 调试模式输出
            $this->mail->isSMTP();                                         // 使用SMTP
            $this->mail->Host = $this->configs['host'];                         // SMTP服务器地址
            $this->mail->SMTPAuth = true;                                // 允许 SMTP 认证
            $this->mail->Username = $this->configs['userAccount'];                     // SMTP 用户名  即邮箱的用户名
            $this->mail->Password = $this->configs['secret'];                       // SMTP 密码  部分邮箱是授权码(例如163邮箱)
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // 允许 TLS 或者ssl协议
            $this->mail->Port = $this->configs['port'];                         // SMTP端口

            //Recipients
            $userName = empty($this->configs['userName']) ? $this->configs['userAccount'] : $this->configs['userName'];
            $this->mail->setFrom($this->configs['userAccount'], $userName); //发件人
            $this->mail->addReplyTo($this->configs['userAccount'], $userName);    //回复的时候回复给哪个邮箱

            // 收件人
            foreach ($this->configs['addressees'] as $addressee) {
                if (empty($addressee['nickName'])) {
                    $addressee['nickName'] = $addressee['email'];
                }
                $this->mail->addAddress($addressee['email'], $addressee['nickName']);
            }
            //抄送
            foreach ($this->configs['ccs'] as $cc) {
                if (empty($cc['nickName'])) {
                    $cc['nickName'] = $cc['email'];
                }
                $this->mail->addCC($cc['email'], $cc['nickName']);
            }
            //密送
            foreach ($this->configs['bccs'] as $bcc) {
                if (empty($bcc['nickName'])) {
                    $bcc['nickName'] = $bcc['email'];
                }
                $this->mail->addBCC($bcc['email'], $bcc['nickName']);
            }

            // Attachments
            foreach ($this->configs['attachments'] as $attachment) {
                if (empty($attachment['fileName'])) {
                    $attachment['fileName'] = basename($attachment['url']);
                }
                $this->mail->addAttachment($attachment['url'], $attachment['fileName']);
            }

            // Content
            $this->mail->isHTML($this->configs['isHtml']);                                  // Set email format to HTML
            $this->mail->Subject = $this->configs['subject'];
            $this->mail->Body = $this->configs['content'];
            $this->mail->AltBody = $this->configs['altContent'];
        } catch (Exception $exception) {    // 捕获phpmailer exception
            return array(-1, $exception->getMessage());
        } catch (\Exception $exception) {   //捕获 exception
            return array(-1, $exception->getMessage());
        }
        return array(0, "ok");
    }

    // set value
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->configs)) {
            $this->configs['name'] = $value;
        } else {
            throw new \Exception('此属性不存在！');
        }
    }

    //get value
    public function __get($name)
    {
        if (array_key_exists($name, $this->configs)) {
            return $this->configs[$name];
        } else {
            throw new \Exception('此属性不存在！');
        }
    }
}