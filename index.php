<?php
  date_default_timezone_set('Asia/Shanghai');
  define("TEMP_DIR", 'temp');
  require_once('oauth/config.php');

  header('Content-Type: text/html; charset=UTF-8');

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['count']) && isset($_POST['lasttime'])) {
      $count = validatecount($_POST['count']);
      $lasttime = validatetime($_POST['lasttime']);
      $tweets = getWeibo($count, $lasttime);
      if ($tweets == array()) {
        exit('认证失败或没有可供更新的微博！<a href="'.php_self().'">返回机器人首页</a>');
      }
      printTweets($tweets);
      exit;
    }
    elseif (isset($_POST['status0']) && isset($_POST['picurl0']) && isset($_POST['time0'])) {
      $tweets = resetTweets($_POST);
      $num = sendTweets($tweets);
      storeLastTime($tweets['tweetsinfo']['time'], $num);
      deletePics($tweets['tweetsinfo']['picpath']);
      exit;
    }
  }
  else {
    //printAuthStatus();
    $lasttime = getLastTime(TEMP_DIR.'/lasttime');
    printOptions($lasttime);
    exit;
  }

  function isWeiboTokenInvalid() {
    require_once('oauth/saetv2.ex.class.php');
    $weibooauth = new SaeTClientV2(WB_AKEY, WB_SKEY, ACCESS_TOKEN);
    $pulic_timeline = $weibooauth->public_timeline(1, 1);

    if (isset($pulic_timeline['statuses'])) {
      return false;
    }
    return true;
  }

  function isTwitterTokenInvalid() {
    require_once('oauth/tmhOAuth.php');
    $connection = new tmhOAuth(array(
      'user_agent' => USER_AGENT,
      'consumer_key' => CONSUMER_KEY,
      'consumer_secret' => CONSUMER_SECRET,
      'user_token' => USER_TOKEN,
      'user_secret' => USER_SECRET
    ));

    if ($connection->request('GET', $connection->url('1.1/account/verify_credentials.json')) !== 200) {
      return true;
    }
    return false;
  }

  function printAuthStatus() {
    if (isTwitterTokenInvalid() || isWeiboTokenInvalid()) {
      header('Content-Type: text/html; charset=UTF-8');
      exit('您的推推或微博授权可能已失效，请<a href="auth.php">重新授权</a>！');
    }
  }

  function getLastTime($uri) {
    if (file_exists($uri)) {
      $lasttime = file_get_contents($uri);
      $lasttime = strtotime($lasttime);
    }
    else {
      $lasttime = time();
    }
    return $lasttime;
  }

  function validatecount($count = 10) {
    $count = intval($count);
    if ($count < 1) {
      $count = 1;
    }
    elseif ($count > 200) {
      $count = 200;
    }
    return $count;
  }

  function validatetime($lasttime = '2012-01-01 00:00:00') {
    $lasttime = strtotime($lasttime);
    if ($lasttime) {
      return $lasttime;
    }
    exit('时间格式无效！<a href="'.php_self().'">返回机器人首页</a>');
  }

  function printOptions($lasttime) { ?>
<!doctype html>
<html>
<head>
<title>获取微博</title>
</head>
<body>
<a href="http://weibo.com/mdjtlt" target="_blank">魔都交通论坛官方微博</a><br />
<a href="auth.php">检测授权状态</a><br /><br />
<form action="<?php echo php_self(); ?>" method="POST">
获取微博数量（1～200）：<br />
<input type="text" name="count" value="10" /><br />
筛选以下时间之后的微博：<br />
<input type="text" name="lasttime" value="<?php echo date('Y-m-d H:i:s', $lasttime); ?>" /><br />
<input type="submit" value="获取微博" />
</form>
</body>
</html>
<?php }

  function php_self($dropqs=true) {
    $protocol = 'http';
    if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
      $protocol = 'https';
    } elseif (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == '443')) {
      $protocol = 'https';
    }

    $url = sprintf('%s://%s%s',
      $protocol,
      $_SERVER['SERVER_NAME'],
      $_SERVER['REQUEST_URI']
    );

    $parts = parse_url($url);

    $port = $_SERVER['SERVER_PORT'];
    $scheme = $parts['scheme'];
    $host = $parts['host'];
    $path = @$parts['path'];
    $qs   = @$parts['query'];

    $port or $port = ($scheme == 'https') ? '443' : '80';

    if (($scheme == 'https' && $port != '443') || ($scheme == 'http' && $port != '80')) {
      $host = "$host:$port";
    }
    $url = "$scheme://$host$path";
    if (!$dropqs) {
      return "{$url}?{$qs}";
    }
    else {
      return $url;
    }
  }

  function getWeibo($count, $lasttime) {
    require_once('oauth/saetv2.ex.class.php');
    $connection = new SaeTClientV2(WB_AKEY, WB_SKEY, ACCESS_TOKEN);
    $weibo = $connection->user_timeline_by_id(2665522961, 1, $count, 0, 0, 1);
    $tweets = array();

    if (isset($weibo['statuses']) && count($weibo['statuses'])) {
      $weibo = $weibo['statuses'];
      $j = -1;
      for ($i = $count-1; $i >= 0; $i--) {
        if (strtotime($weibo[$i]['created_at']) <= $lasttime) {continue;}

        if (preg_match('/自[\d]{4}年[\d]{1,2}月[\d]{1,2}日起/', $weibo[$i]['text'])) {
          $tweets[++$j]['status'] = '#营运动态 '.$weibo[$i]['text'];
        }
        elseif (preg_match('/^(#.*?#)/', $weibo[$i]['text'])) {
          $tweets[++$j]['status'] = trim(preg_replace('/^(#.+?)(#)/', '${1}', $weibo[$i]['text']));
        }
        else {
          $tweets[++$j]['status'] = trim($weibo[$i]['text']);
        }
        $tweets[$j]['picurl'] = getWeiboPicURL($weibo[$i]);
        $tweets[$j]['time'] = strtotime($weibo[$i]['created_at']);
      }
    }
    return $tweets;
  }

  function getWeiboPicURL($status) {
    if (isset($status['original_pic'])) {
      return $status['original_pic'];
    }
    return 'none';
  }

  function printTweets($tweets) {
    $count = count($tweets);
?>
<!doctype html>
<html>
<head>
<title>编辑并发布微博</title>
<script language="javascript">
<!--
  function countlength(status,total,remain) {
    remain.value = total.value - status.value.length;
  }

  function checklength(status,total) {
    if (status.value.length > total.value) {
      return false;
    }
    return true;
  }

  function checklengthall(form) {
    var i = 0;
    for (i = 0; i < <?php echo $count; ?>; i++) {
      if (eval("form.dontsend" + i +".checked")) {
        continue;
      }
      if (eval("checklength(form.status" + i +", form.total" + i +")") === false) {
        alert("需要发布的微博超过字数限制！");
        return false;
      }
    }
    return true;
  }
-->
</script>
</head>
<body>
<form action="<?php echo php_self(); ?>" method="POST" onSubmit="return checklengthall(this);">
<?php
  for ($i = 0; $i < $count; $i++) {
    $status = $tweets[$i]['status'];
    $picurl = $tweets[$i]['picurl'];
    $time = date('Y-m-d H:i:s', $tweets[$i]['time']);
    $maxlength = $picurl == 'none' ? 140 : 114;
    $length = mb_strlen($status, 'UTF8');
?>
微博：<br />
<?php echo '<textarea name="status'.$i.'" cols="60" rows="4" onKeyDown="countlength(this.form.status'.$i.',this.form.total'.$i.',this.form.remain'.$i.');" onKeyUp="countlength(this.form.status'.$i.',this.form.total'.$i.',this.form.remain'.$i.');">'.$status.'</textarea><br />'; ?>
最多字数：
<input disabled maxLength="4" name="total<?php echo $i; ?>" size="3" value="<?php echo $maxlength; ?>">
剩余字数：
<input disabled maxLength="4" name="remain<?php echo $i; ?>" size="3" value="<?php echo $maxlength - $length; ?>">
<input type="checkbox" name="dontsend<?php echo $i; ?>" value="1" />不发布<br />
<?php
  if ($picurl !== 'none') {
?>
图片链接：<a href="<?php echo $picurl; ?>" target="_blank">查看大图</a>
<?php } ?>
<input type="hidden" name="picurl<?php echo $i; ?>" value="<?php echo $picurl; ?>"><br />
发布时间：<?php echo $time; ?>
<input type="hidden" name="time<?php echo $i; ?>" value="<?php echo $time; ?>"><br /><br /><?php } ?>
<input type="submit" value="发布到推推" />
</form>
</body>
</html>
<?php }

  function resetTweets($post) {
    $tweets = array();
    $tweetsinfo = array();
    $tweetsinfo['picpath'] = array();
    for ($i = 0; isset($post["status{$i}"]); $i++) {
      if (isset($post["dontsend{$i}"])) {
        $post["status{$i}"] = 'none';
        $post["picurl{$i}"] = 'none';
      }
      $tweets[$i]['status'] = $post["status{$i}"];
      $pic = storeWeiboPicToTweet($post["picurl{$i}"]);
      if ($pic) {
        $tweets[$i]['media[]'] = $pic['pic'];
        $tweetsinfo['picpath'][] = $pic['path'];
      }
      $tweetsinfo['time'][$i] = $post["time{$i}"];
    }

    return array('tweets' => $tweets, 'tweetsinfo' => $tweetsinfo);
  }

  function storeWeiboPicToTweet($url) {
    if ($url !== 'none') {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
      $pic = curl_exec($ch);
      curl_close($ch);

      $path = dirname(__FILE__).'/'.TEMP_DIR.'/'.basename($url);
      file_put_contents($path, $pic);
      $meta = getimagesize($path);
      $pic = '@'.$path.';type='.$meta['mime'];
      return array('pic' => $pic, 'path' => $path);
    }
    return false;
  }

  function storeLastTime($time, $num) {
    if (count($time)) {
      return file_put_contents(TEMP_DIR.'/lasttime', $time[$num]);
    }
  }

  function sendTweets($tweets) {
    require_once('oauth/tmhOAuth.php');
    $connection = new tmhOAuth(array(
      'user_agent' => USER_AGENT,
      'consumer_key' => CONSUMER_KEY,
      'consumer_secret' => CONSUMER_SECRET,
      'user_token' => USER_TOKEN,
      'user_secret' => USER_SECRET,
      'curl_timeout' => 60
    ));

    $count = count($tweets['tweets']);
    for ($i = 0; $i < $count; $i++) {
      if ($tweets['tweets'][$i]['status'] == 'none') {
        echo "取消发布<br />\n";
        continue;
      }
      if (isset($tweets['tweets'][$i]['media[]'])) {
        $connection->request('POST',
          'https://api.twitter.com/1.1/statuses/update_with_media.json',
          $tweets['tweets'][$i],
          true,true
        );
      }
      else {
        $connection->request('POST',
          $connection->url('1.1/statuses/update.json'),
          $tweets['tweets'][$i]
        );
      }

      $code = $connection->response['code'];
      if ($code !== 200) {
        echo "发布失败，停止发布剩余微博！<br />\n错误代码：{$code}<br />";
        break;
      }
      else {
        echo "发布成功<br />\n";
      }
    }
    echo "<br />\n<a href=\"".php_self().'">返回机器人首页</a>';
    $i == $count && $i--;
    return $i;
  }

  function deletePics($path) {
    if ($path == array()) {
      return 0;
    }

    reset($path);
    do {
      unlink(current($path));
    } while(next($path));
  }
?>
