{__NOLAYOUT__}<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{:__('Warning')}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="__CDN__/assets/img/favicon.ico" />
    <style type="text/css">
        *{box-sizing:border-box;margin:0;padding:0;font-family:Lantinghei SC,Open Sans,Arial,Hiragino Sans GB,Microsoft YaHei,"微软雅黑",STHeiti,WenQuanYi Micro Hei,SimSun,sans-serif;-webkit-font-smoothing:antialiased}
        body{padding:70px 50px;background:#f4f6f8;font-weight:400;font-size:1pc;-webkit-text-size-adjust:none;color:#333}
        a{outline:0;color:#3498db;text-decoration:none;cursor:pointer}
        .system-message{margin:20px auto;padding:50px 0px;background:#fff;box-shadow:0 0 30px hsla(0,0%,39%,.06);text-align:center;width:100%;border-radius:2px;}
        .system-message h1{margin:0;margin-bottom:9pt;color:#444;font-weight:400;font-size:30px}
        .system-message .jump,.system-message .image{margin:20px 0;padding:0;padding:10px 0;font-weight:400}
        .system-message .jump{font-size:14px}
        .system-message .jump a{color:#333}
        .system-message p{font-size:9pt;line-height:20px}
        .system-message .btn{display:inline-block;margin-right:10px;width:138px;height:2pc;border:1px solid #44a0e8;border-radius:30px;color:#44a0e8;text-align:center;font-size:1pc;line-height:2pc;margin-bottom:5px;}
        .success .btn{border-color:#69bf4e;color:#69bf4e}
        .error .btn{border-color:#ff8992;color:#ff8992}
        .info .btn{border-color:#3498db;color:#3498db}
        .copyright p{width:100%;color:#919191;text-align:center;font-size:10px}
        .system-message .btn-grey{border-color:#bbb;color:#bbb}
        .clearfix:after{clear:both;display:block;visibility:hidden;height:0;content:"."}
        @media (max-width:768px){body {padding:20px;}}
        @media (max-width:480px){.system-message h1{font-size:30px;}}
    </style>
</head>
<body>
{php}$codeText=$code == 1 ? 'success' : ($code == 0 ? 'error' : 'info');{/php}
<div class="system-message {$codeText}">
    <div class="image">
        <img src="__CDN__/assets/img/{$codeText}.svg" alt="" width="120" />
    </div>
    <h1>{$msg}</h1>
    {if $url}
        <p class="jump">
            {:__('This page will be re-directed in %s seconds', '<span id="wait">' . $wait . '</span>')}
        </p>
    {/if}
    <p class="clearfix">
        <a href="__PUBLIC__" class="btn btn-grey">{:__('Go back')}</a>
        {if $url}
            <a id="href" href="{$url|htmlentities}" class="btn btn-primary">{:__('Jump now')}</a>
        {/if}
    </p>
</div>
{if $url}
    <script type="text/javascript">
        (function () {
            var wait = document.getElementById('wait'),
                href = document.getElementById('href').href;
            var interval = setInterval(function () {
                var time = --wait.innerHTML;
                if (time <= 0) {
                    location.href = href;
                    clearInterval(interval);
                }
            }, 1000);
        })();
    </script>
{/if}
</body>
</html>