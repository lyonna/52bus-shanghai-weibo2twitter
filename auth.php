<?php
  require_once('oauth/config.php');
  whattodo();

  function whattodo() {
    if (isset($_REQUEST['weibo'])) {
      loadClass('weibo');
      $here = php_self();
      weiboAuthorize($here);
      exitAuth('weibo', $here);
    }
    elseif (isset($_REQUEST['code'])) {
      loadClass('weibo');
      $here = php_self();
      $weibo_token = weiboCode2Token($here);
      if ($weibo_token) {
        header('Content-Type: text/html; charset=UTF-8');
        saveWeiboToken($weibo_token) && printWeiboOauthStatus($weibo_token['expires_in'], $here);
      }
      exitAuth('weibo', $here);
    }
    elseif (isset($_REQUEST['twitter'])) {
      session_start();
      loadClass('twitter');
      $here = php_self();
      twitterAuthorize($here);
      exitAuth('twitter', $here);
    }
    elseif (isset($_REQUEST['oauth_verifier'])) {
      session_start();
      loadClass('twitter');
      $here = php_self();
      twitterToken2Token();
      header('Content-Type: text/html; charset=UTF-8');
      saveTwitterToken() && printTwitterOauthStatus($here);
      exitAuth('twitter', $here);
    }
    elseif (isset($_REQUEST['wipe'])) {
      wipesession(php_self());
    }
    else {
      header('Content-Type: text/html; charset=UTF-8');
      loadClass('both');
      printAuthStatus();
      printOAuthLink();
      printTweetbotLink();
      exit;
    }
  }

  function loadClass($type = 'both') {
    switch($type) {
      case 'weibo':
        require_once('oauth/saetv2.ex.class.php');
        break;
      case 'twitter':
        require_once('oauth/tmhOAuth.php');
        break;
      case 'both':
      default:
        require_once('oauth/saetv2.ex.class.php');
        require_once('oauth/tmhOAuth.php');
    }
  }

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

  function wipesession($url) {
    session_destroy();
    header('Location: '.$url);
  }

  function isWeiboTokenValid() {
    $weibooauth = new SaeTClientV2(WB_AKEY, WB_SKEY, ACCESS_TOKEN);
    $pulic_timeline = $weibooauth->public_timeline(1, 1);

    if (isset($pulic_timeline['statuses'])) {
      return true;
    }
    return false;
  }

  function isTwitterTokenValid() {
    $connection = new tmhOAuth(array(
      'user_agent' => USER_AGENT,
      'consumer_key' => CONSUMER_KEY,
      'consumer_secret' => CONSUMER_SECRET,
      'user_token' => USER_TOKEN,
      'user_secret' => USER_SECRET
    ));

    if ($connection->request('GET', $connection->url('1.1/account/verify_credentials.json')) !== 200) {
      return false;
    }
    return true;
  }

  function addLabelli($string) {
    return "  <li>{$string}</li>\n";
  }

  function printAuthStatus() {
    $twitter_oauth_status = isTwitterTokenValid() ? '您的推推已授权本应用，永久有效！' : '您的推推授权可能已失效，建议重新授权！';
    $weibo_oauth_status = isWeiboTokenValid() ? '您的微博已授权本应用，目前有效！' : '您的微博授权可能已失效，建议重新授权！';
    echo "<ul>\n".addLabelli($twitter_oauth_status).addLabelli($weibo_oauth_status)."</ul>\n";
  }

  function printOAuthLink() { ?>
<ul>
  <li><a href="?twitter=1">在推推上重新授权</a></li>
  <li><a href="?weibo=1">在微博上重新授权</a></li>
</ul>
<?php }

  function printTweetbotLink() { ?>
<ul>
  <li><a href="index.php">打开微博同步应用</a></li>
</ul>
<?php }

  function weiboAuthorize($url) {
    $oauth = new SaeTOAuthV2(WB_AKEY, WB_SKEY);
    header('Location: '.$oauth->getAuthorizeURL($url));
  }

  function weiboCode2Token($url) {
    $oauth = new SaeTOAuthV2(WB_AKEY, WB_SKEY);
    $keys = array();
    $keys['code'] = $_REQUEST['code'];
    $keys['redirect_uri'] = $url;
    return $oauth->getAccessToken('code', $keys);
  }

  function saveWeiboToken($weibo_token) {
    $config_file = file_get_contents('oauth/config.php');
    $config_file = preg_replace('/\"ACCESS_TOKEN\", \'([\w\.]*)\'/', '"ACCESS_TOKEN", \''.$weibo_token['access_token'].'\'', $config_file);
    return file_put_contents('oauth/config.php', $config_file);
  }

  function printWeiboOauthStatus($expires_in, $url) {
    $expires_in = $expires_in == 86399 ? '24小时' : $expires_in.'秒';
    exit('在微博上授权成功！有效期为'.$expires_in.'。<a href="'.$url.'">返回授权状态页</a>');
  }

  function twitterAuthorize($url) {
    $connection = new tmhOAuth(array(
      'user_agent' => USER_AGENT,
      'consumer_key' => CONSUMER_KEY,
      'consumer_secret' => CONSUMER_SECRET
    ));

    if ($connection->request('POST', $connection->url('oauth/request_token', ''), array('oauth_callback' => $url)) == 200) {
      $_SESSION['oauth'] = $connection->extract_params($connection->response['response']);
      $authurl = $connection->url('oauth/authorize', '')."?oauth_token={$_SESSION['oauth']['oauth_token']}";
      header('Location: '.$authurl);
    }
  }

  function twitterToken2Token() {
    $connection = new tmhOAuth(array(
      'user_agent' => USER_AGENT,
      'consumer_key' => CONSUMER_KEY,
      'consumer_secret' => CONSUMER_SECRET,
      'user_token' => $_SESSION['oauth']['oauth_token'],
      'user_secret' => $_SESSION['oauth']['oauth_token_secret']
    ));

    if ($connection->request('POST', $connection->url('oauth/access_token', ''), array('oauth_verifier' => $_REQUEST['oauth_verifier'])) == 200) {
      $_SESSION['access_token'] = $connection->extract_params($connection->response['response']);
    }
  }

  function saveTwitterToken() {
    $config_file = file_get_contents('oauth/config.php');
    $config_file = preg_replace('/\"USER_TOKEN\", \'([\w-]*)\'/', '"USER_TOKEN", \''.$_SESSION['access_token']['oauth_token'].'\'', $config_file);
    $config_file = preg_replace('/\"USER_SECRET\", \'([\w-]*)\'/', '"USER_SECRET", \''.$_SESSION['access_token']['oauth_token_secret'].'\'', $config_file);
    unset($_SESSION['oauth']);
    unset($_SESSION['access_token']);
    return file_put_contents('oauth/config.php', $config_file);
  }

  function printTwitterOauthStatus($url) {
    exit('在推推上授权成功！永久有效。<a href="'.$url.'">返回授权状态页</a>');
  }

  function exitAuth($type, $url) {
    $type == 'weibo' && $string = '微博';
    $type == 'twitter' && $string = '推推';
    exit('在'.$type.'上授权失败！<a href="'.$url.'">返回授权状态页</a>');
  }

?>
