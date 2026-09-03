<?php

namespace app\common\library;

use think\Config;
use Tx\Mailer;
use Tx\Mailer\Exceptions\CodeException;
use Tx\Mailer\Exceptions\SendException;

class Email
{

    /**
     * 单例对象
     */
    protected static $instance;

    /**
     * phpmailer对象
     */
    protected $mail = null;

    /**
     * 错误内容
     */
    protected $error = '';

    /**
     * 默认配置
     */
    public $options = [
        'charset'   => 'utf-8', //编码格式
        'debug'     => false, //调式模式
        'mail_type' => 0, //状态
    ];

    /**
     * 初始化
     * @access public
     * @param array $options 参数
     * @return Email
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 构造函数
     * @param array $options
     */
    public function __construct($options = [])
    {
        if ($config = Config::get('site')) {
            $this->options = array_merge($this->options, $config);
        }
        $this->options = array_merge($this->options, $options);
        $secureArr = [0 => '', 1 => 'tls', 2 => 'ssl'];
        $secure = $secureArr[$this->options['mail_verify_type']] ?? '';

        $logger = isset($this->options['debug']) && $this->options['debug'] ? new Log : null;
        $this->mail = new Mailer($logger);
        $this->mail->setServer($this->options['mail_smtp_host'], $this->options['mail_smtp_port'], $secure);
        $this->mail->setAuth($this->options['mail_from'], $this->options['mail_smtp_pass']);

        //设置发件人
        $this->from($this->options['mail_from'], $this->options['mail_smtp_user']);
    }

    /**
     * 设置邮件主题
     * @param string $subject 邮件主题
     * @return $this
     */
    public function subject($subject)
    {
        $this->mail->setSubject($subject);
        return $this;
    }

    /**
     * 设置发件人
     * @param string $email 发件人邮箱
     * @param string $name  发件人名称
     * @return $this
     */
    public function from($email, $name = '')
    {
        $this->mail->setFrom($name, $email);
        return $this;
    }

    /**
     * 设置收件人
     * @param mixed $email 收件人,多个收件人以,进行分隔
     * @return $this
     */
    public function to($email)
    {
        $emailArr = $this->buildAddress($email);
        foreach ($emailArr as $address => $name) {
            $this->mail->addTo($name, $address);
        }

        return $this;
    }

    /**
     * 设置抄送
     * @param mixed  $email 收件人,多个收件人以,进行分隔
     * @param string $name  收件人名称
     * @return Email
     */
    public function cc($email, $name = '')
    {
        $emailArr = $this->buildAddress($email);
        if (count($emailArr) == 1 && $name) {
            $emailArr[key($emailArr)] = $name;
        }
        foreach ($emailArr as $address => $name) {
            $this->mail->addCC($name, $address);
        }
        return $this;
    }

    /**
     * 设置密送
     * @param mixed  $email 收件人,多个收件人以,进行分隔
     * @param string $name  收件人名称
     * @return Email
     */
    public function bcc($email, $name = '')
    {
        $emailArr = $this->buildAddress($email);
        if (count($emailArr) == 1 && $name) {
            $emailArr[key($emailArr)] = $name;
        }
        foreach ($emailArr as $address => $name) {
            $this->mail->addBCC($name, $address);
        }
        return $this;
    }

    /**
     * 设置邮件正文
     * @param string  $body   邮件下方
     * @param boolean $ishtml 是否HTML格式
     * @return $this
     */
    public function message($body, $ishtml = true)
    {
        $this->mail->setBody($body);
        return $this;
    }

    /**
     * 添加附件
     * @param string $path 附件路径
     * @param string $name 附件名称
     * @return Email
     */
    public function attachment($path, $name = '')
    {
        $this->mail->addAttachment($name, $path);
        return $this;
    }

    /**
     * 构建Email地址
     * @param mixed $emails Email数据
     * @return array
     */
    protected function buildAddress($emails)
    {
        if (!is_array($emails)) {
            $emails = array_flip(explode(',', str_replace(";", ",", $emails)));
            foreach ($emails as $key => $value) {
                $emails[$key] = strstr($key, '@', true);
            }
        }
        return $emails;
    }

    /**
     * 获取最后产生的错误
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 设置错误
     * @param string $error 错误信息
     */
    protected function setError($error)
    {
        $this->error = $error;
    }

    /**
     * 发送邮件
     * @return boolean
     */
    public function send()
    {
        $result = false;
        if (in_array($this->options['mail_type'], [1, 2])) {
            try {
                $result = $this->mail->send();
            } catch (SendException $e) {
                $this->setError($e->getCode() . $e->getMessage());
            } catch (CodeException $e) {
                preg_match_all("/Expected: (\d+)\, Got: (\d+)( \| (.*))?\$/i", $e->getMessage(), $matches);
                $code = $matches[2][0] ?? 0;
                $message = isset($matches[2][0]) && isset($matches[4][0]) ? $matches[4][0] : $e->getMessage();
                $message = mb_convert_encoding($message, 'UTF-8', 'GBK,GB2312,BIG5');
                $this->setError($message);
            } catch (\Exception $e) {
                $this->setError($e->getMessage());
            }

            $this->setError($result ? '' : $this->getError());
        } else {
            //邮件功能已关闭
            $this->setError(__('Mail already closed'));
        }
        return $result;
    }

}
