<?php
include 'vendor/autoload.php';
include 'functions.php';
global $oauth;
global $config;
/*
    帖子 ： https://www.hostloc.com/thread-561971-1-1.html
    github ： https://github.com/qkqpttgf/OneDrive_SCF
*/
$oauth='';
$config='';
$oauth = [
    'onedrive_ver' => 0, // 0:默认（支持商业版与个人版） 1:世纪互联
    'redirect_uri' => 'https://scfonedrive.github.io',
    'refresh_token' => '',
];
$config = [
    'sitename' => getenv('sitename'),
    'passfile' => getenv('passfile'),
    'imgup_path' => getenv('imgup_path'),
];
//在环境变量添加：
/*
sitename       ：网站的名称，不添加会显示为‘请在环境变量添加sitename’
admin          ：管理密码，不添加时不显示登录页面且无法登录
public_path    ：使用API长链接访问时，显示网盘文件的路径，不设置时默认为根目录
private_path   ：使用自定义域名访问时，显示网盘文件的路径，不设置时默认为根目录
imgup_path     ：设置图床路径，不设置这个值时该目录内容会正常列文件出来，设置后只有上传界面，不显示其中文件（登录后显示）
passfile       ：自定义密码文件的名字，可以是'.password'，也可以是'aaaa.txt'等等；
        　       密码是这个文件的内容，可以空格、可以中文；列目录时不会显示，只有知道密码才能查看或下载此文件。
t1,t2,t3,t4,t5,t6,t7：把refresh_token按128字节切开来放在环境变量，方便更新版本
*/

function main_handler($event, $context)
{
    global $oauth;
    global $config;
    $event = json_decode(json_encode($event), true);
    $context = json_decode(json_encode($context), true);
    $event1 = $event;
    if (strlen(json_encode($event1['body']))>150) $event1['body']=substr($event1['body'],0,strpos($event1['body'],'base64')+10) . '...Too Long!...' . substr($event1['body'],-50);
    echo urldecode(json_encode($event1)) . '
 
' . urldecode(json_encode($context)) . '
 
';
    $function_name = $context['function_name'];
    $config['function_name'] = $function_name;
    $host_name = $event['headers']['host'];
    $serviceId = $event['requestContext']['serviceId'];
    if ( $serviceId === substr($host_name,0,strlen($serviceId)) ) {
        $config['base_path'] = '/'.$event['requestContext']['stage'].'/'.$function_name.'/';
        $config['list_path'] = getenv('public_path');
        $path = substr($event['path'], strlen('/'.$function_name.'/'));
    } else {
        $config['base_path'] = getenv('base_path');
        if (empty($config['base_path'])) $config['base_path'] = '/';
        $config['list_path'] = getenv('private_path');
        $path = substr($event['path'], strlen($event['requestContext']['path']));
    }
    if (substr($path,-1)=='/') $path=substr($path,0,-1);
    if (empty($config['list_path'])) {
        $config['list_path'] = '/';
    } else {
        $config['list_path'] = spurlencode($config['list_path'],'/') ;
    }
    if (empty($config['sitename'])) $config['sitename'] = '请在环境变量添加sitename';
    $config['sourceIp'] = $event['requestContext']['sourceIp'];
    unset($_POST);
    unset($_GET);
    unset($_COOKIE);
    $_GET = $event['queryString'];
    $_SERVER['PHP_SELF'] = path_format($config['base_path'] . $path);
    $referer = $event['headers']['referer'];
    $tmpurl = substr($referer,strpos($referer,'//')+2);
    $refererhost = substr($tmpurl,0,strpos($tmpurl,'/'));
    if ($refererhost==$host_name) {
        // 仅游客上传用，referer不对就空值，无法上传
        $config['current_url'] = substr($referer,0,strpos($referer,'//')) . '//' . $host_name.$_SERVER['PHP_SELF'];
    } else {
        $config['current_url'] = '';
    }
    $_POSTbody = explode("&",$event['body']);
    foreach ($_POSTbody as $postvalues){
        $pos = strpos($postvalues,"=");
        $_POST[urldecode(substr($postvalues,0,$pos))]=urldecode(substr($postvalues,$pos+1));
    }
    $cookiebody = explode("; ",$event['headers']['cookie']);
    foreach ($cookiebody as $cookievalues){
        $pos = strpos($cookievalues,"=");
        $_COOKIE[urldecode(substr($cookievalues,0,$pos))]=urldecode(substr($cookievalues,$pos+1));
    }

    config_oauth();
    if (!$config['base_path']) {
        return message('Missing env <code>base_path</code>');
    }
    if (!$oauth['refresh_token']) $oauth['refresh_token'] = getenv('t1').getenv('t2').getenv('t3').getenv('t4').getenv('t5').getenv('t6').getenv('t7');
    if (!$oauth['refresh_token']) {
        if ($path=='authorization_code' && isset($_GET['code'])) {
            return message(get_refresh_token($_GET['code']));
        }
        return message('Please set the <code>refresh_token</code> in environments<br>
    <a href="" id="a1">Get a refresh_token</a>
    <br><code>allow javascript</code>
    <script>
        url=window.location.href;
        if (url.substr(-1)!="/") url+="/";
        url="'. $oauth['oauth_url'] .'authorize?scope='. $oauth['scope'] .'&response_type=code&client_id='. $oauth['client_id'] .'&redirect_uri='. $oauth['redirect_uri'] . '&state=' .'"+encodeURIComponent(url);
        document.getElementById(\'a1\').href=url;
        //window.open(url,"_blank");
    </script>
    ', 'Error', 500);
    }
    if ($_COOKIE[$function_name]==md5(getenv('admin')) && getenv('admin')!='' ) {
        $config['admin']=1;
    } else {
        $config['admin']=0;
    }
    if ($_GET['admin']) {
        $url=$_SERVER['PHP_SELF'];
        if ($_GET['preview']) $url .= '?preview';
        if (getenv('admin')!='') {
            if ($_POST['password1']==getenv('admin')) return adminform($function_name,md5($_POST['password1']),$url);
            return adminform();
        } else {
            return output('', 302, false, [ 'Location' => $url ]);
        }
    }
    $config['ajax']=0;
    if ($event['headers']['x-requested-with']=='XMLHttpRequest') {
        $config['ajax']=1;
    }

    return list_files($path);
}

function config_oauth()
{
    global $oauth;
    if ($oauth['onedrive_ver']==0) {
        // 0 默认（支持商业版与个人版）
        // https://portal.azure.com
        $oauth['client_id'] = '4da3e7f2-bf6d-467c-aaf0-578078f0bf7c';
        $oauth['client_secret'] = '7/+ykq2xkfx:.DWjacuIRojIaaWL0QI6';
        $oauth['oauth_url'] = 'https://login.microsoftonline.com/common/oauth2/v2.0/';
        $oauth['api_url'] = 'https://graph.microsoft.com/v1.0/me/drive/root';
        $oauth['scope'] = 'https://graph.microsoft.com/Files.ReadWrite.All offline_access';
    }
    if ($oauth['onedrive_ver']==1) {
        // 1 世纪互联
        // https://portal.azure.cn
        $oauth['client_id'] = '04c3ca0b-8d07-4773-85ad-98b037d25631';
        $oauth['client_secret'] = 'h8@B7kFVOmj0+8HKBWeNTgl@pU/z4yLB';
        $oauth['oauth_url'] = 'https://login.partner.microsoftonline.cn/common/oauth2/v2.0/';
        $oauth['api_url'] = 'https://microsoftgraph.chinacloudapi.cn/v1.0/me/drive/root';
        $oauth['scope'] = 'https://microsoftgraph.chinacloudapi.cn/Files.ReadWrite.All offline_access';
    }
    if ($oauth['onedrive_ver']==2) {
        // 2 SharePoint
        // https://portal.azure.com
        $oauth['client_id'] = '4214169b-2f35-4ffd-95b0-1b05d55448e5';
        $oauth['client_secret'] = 'iTsch4W@afSadYo.[VLLR[FdfKEri803';
        $oauth['oauth_url'] = 'https://login.microsoftonline.com/common/oauth2/v2.0/';
        $oauth['api_url'] = 'https://graph.microsoft.com/v1.0/me/drive/root';
        $oauth['scope'] = 'https://microsoft.sharepoint-df.com/MyFiles.Read https://microsoft.sharepoint-df.com/MyFiles.Write offline_access';
    }
    $oauth['client_secret'] = urlencode($oauth['client_secret']);
    $oauth['scope'] = urlencode($oauth['scope']);
}

function get_refresh_token($code)
{
    global $oauth;
    $ret = json_decode(curl_request(
        $oauth['oauth_url'] . 'token',
        'client_id='. $oauth['client_id'] .'&client_secret='. $oauth['client_secret'] .'&grant_type=authorization_code&requested_token_use=on_behalf_of&redirect_uri='. $oauth['redirect_uri'] .'&code=' . $code), true);
    if (isset($ret['refresh_token'])) {
        $tmptoken=$ret['refresh_token'];
        $str = 'split:<br>';
        for ($i=1;strlen($tmptoken)>0;$i++) {
            $str .= 't' . $i . ':<textarea readonly style="width: 95%">' . substr($tmptoken,0,128) . '</textarea>';
            $tmptoken=substr($tmptoken,128);
        }
        return '<table width=100%><tr>
        <td>' . $str . '</td>
        <td width=50%>refresh_token:<textarea readonly style="width: 100%;">' . $ret['refresh_token'] . '</textarea></td>
        </tr></table><br><br>
        Please add t1-t'.--$i.' to environments
        <script>
            var texta=document.getElementsByTagName(\'textarea\');
            for(i=0;i<texta.length;i++) {
                texta[i].style.height = texta[i].scrollHeight + \'px\';
            }
        </script>';
    }
    return '<pre>' . json_encode($ret, JSON_PRETTY_PRINT) . '</pre>';
}

function fetch_files($path = '/')
{
    global $oauth;
    global $config;
    $path1 = path_format($path);
    $path = path_format($config['list_path'] . path_format($path));
    $cache = null;
    $cache = new \Doctrine\Common\Cache\FilesystemCache(sys_get_temp_dir(), '.qdrive');
    if (!($files = $cache->fetch('path_' . $path))) {

        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0
        // https://docs.microsoft.com/zh-cn/graph/api/driveitem-put-content?view=graph-rest-1.0&tabs=http
        // https://developer.microsoft.com/zh-cn/graph/graph-explorer

        $url = $oauth['api_url'];
        if ($path !== '/') {
                    $url .= ':' . $path;
                    if (substr($url,-1)=='/') $url=substr($url,0,-1);
                }
        $url .= '?expand=children(select=name,size,file,folder,parentReference,lastModifiedDateTime)';
        $files = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $config['access_token']]), true);
        // echo $path . '<br><pre>' . json_encode($files, JSON_PRETTY_PRINT) . '</pre>';

        if (isset($files['folder'])) {
            if ($files['folder']['childCount']>200) {
                // files num > 200 , then get nextlink
                $page = $_POST['pagenum']==''?1:$_POST['pagenum'];
                $files=fetch_files_children($files, $path, $page, $cache);
            } else {
                // files num < 200 , then cache
                $cache->save('path_' . $path, $files, 60);
            }
        }
    }
    return $files;
}

function fetch_files_children($files, $path, $page, $cache)
{
    global $oauth;
    global $config;
    $cachefilename = '.SCFcache_'.$config['function_name'];
    $maxpage = ceil($files['folder']['childCount']/200);

    if (!($files['children'] = $cache->fetch('files_' . $path . '_page_' . $page))) {
                    // 下载cache文件获取跳页链接
        $cachefile = fetch_files(path_format($path1 . '/' .$cachefilename));
        if ($cachefile['size']>0) {
            $pageinfo = curl_request($cachefile['@microsoft.graph.downloadUrl']);
                        //$cachefilesize = strlen($pageinfo);
            $pageinfo = json_decode($pageinfo,true);
                        //$rsize=$files['size']-$cachefile['size'];
                        //if ($pageinfo['size']==$files['size']) {
            for ($page4=1;$page4<$maxpage;$page4++) {
                $cache->save('nextlink_' . $path . '_page_' . $page4, $pageinfo['nextlink_' . $path . '_page_' . $page4], 60);
                $pageinfocache['nextlink_' . $path . '_page_' . $page4] = $pageinfo['nextlink_' . $path . '_page_' . $page4];
            }
                        //}
        }
        $pageinfochange=0;
        for ($page1=$page;$page1>=1;$page1--) {
            $page3=$page1-1;
            $url = $cache->fetch('nextlink_' . $path . '_page_' . $page3);
            if ($url == '') {
                            //echo $page3 .'not have url'. $url .'<br>' ;
                if ($page1==1) {
                    $url = $oauth['api_url'];
                    if ($path !== '/') {
                        $url .= ':' . $path;
                        if (substr($url,-1)=='/') $url=substr($url,0,-1);
                        $url .= ':/children?$select=name,size,file,folder,parentReference,lastModifiedDateTime';
                    } else {
                        $url .= '/children?$select=name,size,file,folder,parentReference,lastModifiedDateTime';
                    }
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $config['access_token']]), true);
                               // echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                    $cache->save('files_' . $path . '_page_' . $page1, $children['value'], 60);
                    $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page1);
                    if ($nextlink!=$children['@odata.nextLink']) {
                        $cache->save('nextlink_' . $path . '_page_' . $page1, $children['@odata.nextLink'], 60);
                        $pageinfocache['nextlink_' . $path . '_page_' . $page1] = $children['@odata.nextLink'];
                        $pageinfocache = clearbehindvalue($path,$page1,$maxpage,$pageinfocache);
                        $pageinfochange = 1;
                    }
                    $url = $children['@odata.nextLink'];
                    for ($page2=$page1+1;$page2<=$page;$page2++) {
                        sleep(1);
                        $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $config['access_token']]), true);
                                    //echo $page2 . ' ' . $url . '<br>';
                        $cache->save('files_' . $path . '_page_' . $page2, $children['value'], 60);
                        $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page2);
                        if ($nextlink!=$children['@odata.nextLink']) {
                            $cache->save('nextlink_' . $path . '_page_' . $page2, $children['@odata.nextLink'], 60);
                            $pageinfocache['nextlink_' . $path . '_page_' . $page2] = $children['@odata.nextLink'];
                            $pageinfocache = clearbehindvalue($path,$page2,$maxpage,$pageinfocache);
                            $pageinfochange = 1;
                        }
                        $url = $children['@odata.nextLink'];
                    }
                                //echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                    $files['children'] = $children['value'];
                    $files['folder']['page']=$page;
                    $pageinfocache['filenum'] = $files['folder']['childCount'];
                    $pageinfocache['dirsize'] = $files['size'];
                    $pageinfocache['cachesize'] = $cachefile['size'];
                    $pageinfocache['size'] = $files['size']-$cachefile['size'];
                    if ($pageinfochange == 1) echo MSAPI('PUT', path_format($path.'/'.$cachefilename), json_encode($pageinfocache, JSON_PRETTY_PRINT), $config['access_token'])['body'];
                    return $files;
                }
            } else {
                            //echo $page3 .'have url<br> '. $url .'<br> ' ;
                for ($page2=$page3+1;$page2<=$page;$page2++) {
                    sleep(1);
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $config['access_token']]), true);
                                //echo $page2 . ' ' . $url . '<br>';
                    $cache->save('files_' . $path . '_page_' . $page2, $children['value'], 60);
                    $nextlink=$cache->fetch('nextlink_' . $path . '_page_' . $page2);
                    if ($nextlink!=$children['@odata.nextLink']) {
                        $cache->save('nextlink_' . $path . '_page_' . $page2, $children['@odata.nextLink'], 60);
                        $pageinfocache['nextlink_' . $path . '_page_' . $page2] = $children['@odata.nextLink'];
                        $pageinfocache = clearbehindvalue($path,$page2,$maxpage,$pageinfocache);
                        $pageinfochange = 1;
                    }
                    $url = $children['@odata.nextLink'];
                }
                                //echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                $files['children'] = $children['value'];
                $files['folder']['page']=$page;
                $pageinfocache['filenum'] = $files['folder']['childCount'];
                $pageinfocache['dirsize'] = $files['size'];
                $pageinfocache['cachesize'] = $cachefile['size'];
                $pageinfocache['size'] = $files['size']-$cachefile['size'];
                if ($pageinfochange == 1) echo MSAPI('PUT', path_format($path.'/'.$cachefilename), json_encode($pageinfocache, JSON_PRETTY_PRINT), $config['access_token'])['body'];
                return $files;
            }
        }
    } else {
        $files['folder']['page']=$page;
        for ($page4=1;$page4<=$maxpage;$page4++) {
            if (!($url = $cache->fetch('nextlink_' . $path . '_page_' . $page4))) {
                if ($files['folder'][$path.'_'.$page4]!='') $cache->save('nextlink_' . $path . '_page_' . $page4, $files['folder'][$path.'_'.$page4], 60);
            } else {
                $files['folder'][$path.'_'.$page4] = $url;
            }
        }
    }
    return $files;
}

function list_files($path)
{
    global $oauth;
    global $config;
    $is_preview = false;
    if ($_GET['preview']) $is_preview = true;
    $path = path_format($path);
    $cache = null;
    $cache = new \Doctrine\Common\Cache\FilesystemCache(sys_get_temp_dir(), '.qdrive');
    if (!($access_token = $cache->fetch('access_token'))) {
        $ret = json_decode(curl_request(
            $oauth['oauth_url'] . 'token',
            'client_id='. $oauth['client_id'] .'&client_secret='. $oauth['client_secret'] .'&grant_type=refresh_token&requested_token_use=on_behalf_of&refresh_token=' . $oauth['refresh_token']
        ), true);
        if (!isset($ret['access_token'])) {
            error_log('failed to get access_token. response' . json_encode($ret));
            throw new Exception('failed to get access_token.');
        }
        $access_token = $ret['access_token'];
        $config['access_token'] = $access_token;
        $cache->save('access_token', $config['access_token'], $ret['expires_in'] - 60);
    }

    if ($config['ajax']&&$_POST['action']=='del_upload_cache'&&substr($_POST['filename'],-4)=='.tmp') {
        $tmp = MSAPI('DELETE',path_format(path_format($config['list_path'] . path_format($path)) . '/' . spurlencode($_POST['filename']) ),'',$access_token);
        return output($tmp['body'],$tmp['stat']);
    } 
    if ($config['admin']) {
        $tmp = adminoperate($path);
        /*if ($tmp['statusCode'] == 403 || $tmp['statusCode'] == 200) {
            return $tmp;
        }*/
        if ($tmp['statusCode'] > 0) {
            $path1 = path_format($config['list_path'] . path_format($path));
            $cache->save('path_' . $path1, json_decode('{}',true), 1);
            return $tmp;
        }
    } else {
        if ($config['ajax']) return output('请重新<a href="?admin"><font color="red">登录</font></a>',401);
        if (path_format('/'.path_format(urldecode($config['list_path'].$path)).'/')==path_format('/'.path_format($config['imgup_path']).'/')&&$config['imgup_path']!='') {
            $html = guestupload($path);
            if ($html!='') return $html;
        }
    }
    $config['ishidden'] = 4;
    $config['ishidden'] = passhidden($path);
    if (path_format('/'.path_format(urldecode($config['list_path'].$path)).'/')==path_format('/'.path_format($config['imgup_path']).'/')&&$config['imgup_path']!=''&&!$config['admin']) {
        // 是图床目录且不是管理
        $files = json_decode('{"folder":{}}', true);
    } elseif ($config['ishidden']==4) {
        $files = json_decode('{"folder":{}}', true);
    } else {
        $files = fetch_files($path);
    }
    if (isset($files['file']) && !$is_preview) {
        // is file && not preview mode
        //if ($config['admin'] or $ishidden<4) {
        if ($config['ishidden']<4) {
            return output('', 302, false, [
                'Location' => $files['@microsoft.graph.downloadUrl']
            ]);
        }
    }
    // return '<pre>' . json_encode($files, JSON_PRETTY_PRINT) . '</pre>';
    return render_list($path, $files);
}

function adminform($name = '', $pass = '', $path = '')
{
    $statusCode = 401;
    $html = '<html><head><title>管理登录</title><meta charset=utf-8></head>';
    if ($name!='') {
        $html .= '<script type="text/javascript">
            var expd = new Date();
            expd.setTime(expd.getTime()+(1*60*60*1000));
            var expires = "expires="+expd.toGMTString();
            document.cookie="'.$name.'='.$pass.';"+expires;
            //path='.$path.';
            location.href=location.protocol + "//" + location.host + "'.$path.'";
</script>';
        $statusCode = 302;
    }
    $html .= '
    <body>
	<div>
	  <center><h4>输入管理密码</h4>
	  <form action="" method="post">
		  <div>
		    <label>密码</label>
		    <input class="password" name="password1" type="password"/>
		    <button class="submit" type="submit">查看</button>
          </div>
	  </form>
      </center>
	</div>
';
    $html .= '</body></html>';
    return output($html,$statusCode);
}

function guestupload($path)
{
    global $config;
    $path1 = path_format($config['list_path'] . path_format($path));
    if (substr($path1,-1)=='/') $path1=substr($path1,0,-1);
    if ($_POST['guest_upload_filecontent']!=''&&$_POST['upload_filename']!='') if ($config['current_url']!='') {
        $data = substr($_POST['guest_upload_filecontent'],strpos($_POST['guest_upload_filecontent'],'base64')+strlen('base64,'));
        $data = base64_decode($data);
            // 重命名为MD5加后缀
        $filename = spurlencode($_POST['upload_filename']);
        $ext = strtolower(substr($filename, strrpos($filename, '.')));
        $tmpfilename = "tmp/".date("Ymd-His")."-".$filename;
        $tmpfile=fopen($tmpfilename,'wb');
        fwrite($tmpfile,$data);
        fclose($tmpfile);
        $filename = md5_file($tmpfilename) . $ext;
        $locationurl = $config['current_url'] . '/' . $filename . '?preview';
        $response=MSAPI('createUploadSession',path_format($path1 . '/' . $filename),'{"item": { "@microsoft.graph.conflictBehavior": "fail"  }}',$config['access_token'])['body'];
        $responsearry=json_decode($response,true);
        if (isset($responsearry['error'])) return message($responsearry['error']['message']. '<hr><a href="' . $locationurl .'">' . $filename . '</a><br><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $uploadurl=$responsearry['uploadUrl'];
        $result = MSAPI('PUT',$uploadurl,$data,$config['access_token'])['body'];
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        return output('', 302, false, [ 'Location' => $locationurl ]);
    } else {
        return message('Please upload from source site!');
    }
}

function adminoperate($path)
{
    global $config;
    $path1 = path_format($config['list_path'] . path_format($path));
    if (substr($path1,-1)=='/') $path1=substr($path1,0,-1);
    $tmparr['statusCode'] = 0;
    if ($_POST['upbigfilename']!=''&&$_POST['filesize']>0) {
        $fileinfo['name'] = $_POST['upbigfilename'];
        $fileinfo['size'] = $_POST['filesize'];
        $fileinfo['lastModified'] = $_POST['lastModified'];
        $filename = spurlencode( $fileinfo['name'] );
        $cachefilename = '.' . $fileinfo['lastModified'] . '_' . $fileinfo['size'] . '_' . $filename . '.tmp';
        $getoldupinfo=fetch_files(path_format($path . '/' . $cachefilename));
        //echo json_encode($getoldupinfo, JSON_PRETTY_PRINT);
        if (isset($getoldupinfo['file'])&&$getoldupinfo['size']<5120) {
            $getoldupinfo_j = curl_request($getoldupinfo['@microsoft.graph.downloadUrl']);
            $getoldupinfo = json_decode($getoldupinfo_j , true);
            //if ($getoldupinfo['size']==$fileinfo['size'] && $getoldupinfo['lastModified']==$fileinfo['lastModified']) {
                /*$expirationDateTime = time_format( json_decode( curl_request($getoldupinfo['uploadUrl']), true)['expirationDateTime'] );
                if (time() < strtotime($expirationDateTime)) return output($getoldupinfo_j);*/
                //微软的过期时间只有20分钟，其实不用看过期时间，我过了14个小时，用昨晚的链接还可以接着继续上传，微软临时文件只要还在就可以续
                if ( json_decode( curl_request($getoldupinfo['uploadUrl']), true)['@odata.context']!='' ) return output($getoldupinfo_j);
            //}
        }
        $response=MSAPI('createUploadSession',path_format($path1 . '/' . $filename),'{"item": { "@microsoft.graph.conflictBehavior": "fail"  }}',$config['access_token'])['body'];
        $responsearry = json_decode($response,true);
        if (isset($responsearry['error'])) return output($response);
        $fileinfo['uploadUrl'] = $responsearry['uploadUrl'];
        echo MSAPI('PUT', path_format($path1 . '/' . $cachefilename), json_encode($fileinfo, JSON_PRETTY_PRINT), $config['access_token'])['body'];
        return output($response);
    }
    /*if ($_POST['upload_filename']!='') {
        // 上传
        $filename = spurlencode($_POST['upload_filename']);
        $data = substr($_POST['upload_filecontent'],strpos($_POST['upload_filecontent'],'base64')+strlen('base64,'));
        $data = base64_decode($data);
        $response=MSAPI('createUploadSession',path_format($path1 . '/' . $filename),'{"item": { "@microsoft.graph.conflictBehavior": "rename"  }}',$config['access_token'])['body'];
        $responsearry = json_decode($response,true);
        if (isset($responsearry['error'])) return message($responsearry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $uploadurl=$responsearry['uploadUrl'];
                    /*$datasplit=$data;
                    while ($datasplit!='') {
                        $tmpdata=substr($datasplit,0,1024000);
                        $datasplit=substr($datasplit,1024000);
                        echo MSAPI('PUT',$uploadurl,$tmpdata,$config['access_token']);
                    }//大文件循环PUT，SCF用不上*
        $result = MSAPI('PUT',$uploadurl,$data,$config['access_token'])['body'];
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;
    }*/
    if ($_POST['rename_newname']!=$_POST['rename_oldname'] && $_POST['rename_newname']!='') {
        // 重命名
        $oldname = spurlencode($_POST['rename_oldname']);
        $oldname = path_format($path1 . '/' . $oldname);
        $data = '{"name":"' . $_POST['rename_newname'] . '"}';
                //echo $oldname;
        $result = MSAPI('PATCH',$oldname,$data,$config['access_token']);
        /*echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;*/
        return output($result['body'], $result['stat']);
    }
    if ($_POST['delete_name']!='') {
        // 删除
        $filename = spurlencode($_POST['delete_name']);
        $filename = path_format($path1 . '/' . $filename);
                //echo $filename;
        $result = MSAPI('DELETE', $filename, '', $config['access_token']);
        /*echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message'] . '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;*/
        return output($result['body'], $result['stat']);
    }
    if ($_POST['operate_action']=='加密') {
        // 加密
        if ($config['passfile']=='') return message('先在环境变量设置passfile才能加密','',403);
        if ($_POST['encrypt_folder']=='/') $_POST['encrypt_folder']=='';
        $foldername = spurlencode($_POST['encrypt_folder']);
        $filename = path_format($path1 . '/' . $foldername . '/' . $config['passfile']);
                //echo $foldername;
        $result = MSAPI('PUT', $filename, $_POST['encrypt_newpass'], $config['access_token']);
        /*echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;*/
        //echo $result['body'];
        return output($result['body'], $result['stat']);
    }
    if ($_POST['move_folder']!='') {
        // 移动
        $moveable = 1;
        if ($path == '/' && $_POST['move_folder'] == '/../') $moveable=0;
        if ($_POST['move_folder'] == $_POST['move_name']) $moveable=0;
        if ($moveable) {
            $filename = spurlencode($_POST['move_name']);
            $filename = path_format($path1 . '/' . $filename);
                //echo $filename;
            $foldername = path_format('/'.urldecode($path1).'/'.$_POST['move_folder']);
            $data = '{"parentReference":{"path": "/drive/root:'.$foldername.'"}}';
                // echo $data;
            $result = MSAPI('PATCH', $filename, $data, $config['access_token']);
            /*echo $result;
            $resultarry = json_decode($result,true);
            if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
            $tmparr['statusCode'] = 201;*/
            return output($result['body'], $result['stat']);
        } else {
            return output('{"error":"无法移动"}', 403);
        }
    }
    if ($_POST['editfile']!='') {
        // 编辑
        $data = $_POST['editfile'];
        /*TXT一般不会超过4M，不用二段上传
        $filename = $path1 . ':/createUploadSession';
        $response=MSAPI('POST',$filename,'{"item": { "@microsoft.graph.conflictBehavior": "replace"  }}',$config['access_token']);
        $uploadurl=json_decode($response,true)['uploadUrl'];
        echo MSAPI('PUT',$uploadurl,$data,$config['access_token']);*/
        $result = MSAPI('PUT', $path1, $data, $config['access_token'])['body'];
        echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);
        $tmparr['statusCode'] = 201;
    }
    if ($_POST['create_name']!='') {
        // 新建
        if ($_POST['create_type']=='file') {
            $filename = spurlencode($_POST['create_name']);
            $filename = path_format($path1 . '/' . $filename);
            $result = MSAPI('PUT', $filename, $_POST['create_text'], $config['access_token']);
            /*echo $result;
            $resultarry = json_decode($result,true);
            if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);*/
        }
        if ($_POST['create_type']=='folder') {
            $data = '{ "name": "' . $_POST['create_name'] . '",  "folder": { },  "@microsoft.graph.conflictBehavior": "rename" }';
            $result = MSAPI('children', $path1, $data, $config['access_token']);
            /*echo $result;
            $resultarry = json_decode($result,true);
            if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">上一页</a>','错误',403);*/
        }
        //$tmparr['statusCode'] = 201;
        return output($result['body'], $result['stat']);
    }
    return $tmparr;
}

function MSAPI($method, $path, $data = '', $access_token)
{
    global $oauth;
    // 移目录，echo MSAPI('PATCH','/public/qqqq.txt','{"parentReference":{"path": "/drive/root:/public/release"}}',$access_token);
    // 改名，echo MSAPI('PATCH','/public/qqqq.txt','{"name":"f.txt"}',$access_token);
    // 删除，echo MSAPI('DELETE','/public/qqqq.txt','',$access_token);
    // echo $method. $path.$data;
    if (substr($path,0,7) == 'http://' or substr($path,0,8) == 'https://') {
        $url=$path;
        $lenth=strlen($data);
        $headers['Content-Length'] = $lenth;
        $lenth--;
        $headers['Content-Range'] = 'bytes 0-' . $lenth . '/' . $headers['Content-Length'];
    } else {
        $url = $oauth['api_url'];
        if ($path=='' or $path=='/') {
            $url .= '/';
        } else {
            $url .= ':' . $path;
            if (substr($url,-1)=='/') $url=substr($url,0,-1);
        }
        if ($method=='PUT') {
            if ($path=='' or $path=='/') {
                $url .= 'content';
            } else {
                $url .= ':/content';
            }
            $headers['Content-Type'] = 'text/plain';
        } elseif ($method=='PATCH') {
            $headers['Content-Type'] = 'application/json';
        } elseif ($method=='POST') {
            $headers['Content-Type'] = 'application/json';
        } elseif ($method=='DELETE') {
            $headers['Content-Type'] = 'application/json';
        } else {
            if ($path=='' or $path=='/') {
                $url .= $method;
            } else {
                $url .= ':/' . $method;
            }
            $method='POST';
            $headers['Content-Type'] = 'application/json';
        }
    }
    $headers['Authorization'] = 'Bearer ' . $access_token;
    if (!isset($headers['Accept'])) $headers['Accept'] = '*/*';
    if (!isset($headers['Referer'])) $headers['Referer'] = $url;
    $sendHeaders = array();
    foreach ($headers as $headerName => $headerVal) {
        $sendHeaders[] = $headerName . ': ' . $headerVal;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    /*if ($method=='PUT') {
        #curl_setopt($ch, CURLOPT_PUT, 1);
        #curl_setopt($ch, CURLOPT_INFILE, $data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    }
    if ($method=='PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    }*/
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data);

    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 返回获取的输出文本流
    curl_setopt($ch, CURLOPT_HEADER, 0);         // 将头文件的信息作为数据流输出
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);
    $response['body'] = curl_exec($ch);
    $response['stat'] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo $response['stat'].'
';
    return $response;
}

function clearbehindvalue($path,$page1,$maxpage,$pageinfocache)
{
    for ($page=$page1+1;$page<$maxpage;$page++) {
        $pageinfocache['nextlink_' . $path . '_page_' . $page] = '';
    }
    return $pageinfocache;
}

function encode_str_replace($str)
{
    $str = str_replace('&','&amp;',$str);
    $str = str_replace('+','%2B',$str);
    $str = str_replace('#','%23',$str);
    return $str;
}

function render_list($path, $files)
{
    global $config;
    @ob_start();
    date_default_timezone_set('Asia/Shanghai');
    if ($_COOKIE['timezone']!=' 0800') date_default_timezone_set(get_timezone($_COOKIE['timezone']));
    $path = str_replace('%20','%2520',$path);
    $path = str_replace('+','%2B',$path);
    $path = str_replace('&','&amp;',path_format(urldecode($path))) ;
    $path = str_replace('%20',' ',$path);
    $path = str_replace('#','%23',$path);
    $p_path='';
    if ($path !== '/') {
        if (isset($files['file'])) {
            $pretitle = str_replace('&','&amp;', $files['name']);
            $n_path=$pretitle;
        } else {
            $pretitle = substr($path,-1)=='/'?substr($path,0,-1):$path;
            $n_path=substr($pretitle,strrpos($pretitle,'/')+1);
            $pretitle = substr($pretitle,1);
        }
        if (strrpos($path,'/')!=0) {
            $p_path=substr($path,0,strrpos($path,'/'));
            $p_path=substr($p_path,strrpos($p_path,'/')+1);
        }
    } else {
      $pretitle = '首页';
      $n_path=$pretitle;
    }
    $n_path=str_replace('&amp;','&',$n_path);
    $p_path=str_replace('&amp;','&',$p_path);
    $statusCode=200;
    ?>
    <!DOCTYPE html>
    <html lang="zh-cn">
    <head>
        <!-- <script>if (location.protocol === 'http:') location.href = location.href.replace(/http/, 'https');</script> -->
        <title><?php echo $pretitle;?> - <?php echo $config['sitename'];?></title>
        <!--
            帖子 ： https://www.hostloc.com/thread-561971-1-1.html
            github ： https://github.com/qkqpttgf/OneDrive_SCF
        -->
        <meta charset=utf-8>
        <meta http-equiv=X-UA-Compatible content="IE=edge">
        <meta name=viewport content="width=device-width,initial-scale=1">
        <meta name="keywords" content="<?php echo $n_path;?>,<?php if ($p_path!='') echo $p_path.','; echo $config['sitename'];?>,OneDrive_SCF,auth_by_逸笙">
        <link rel="icon" href="<?php echo $config['base_path'];?>favicon.ico" type="image/x-icon" />
        <link rel="shortcut icon" href="<?php echo $config['base_path'];?>favicon.ico" type="image/x-icon" />
        <link rel="shortcut icon" href="//vcheckzen.github.io/favicon.ico">
        <style type="text/css">
            /* latin */
            @font-face {
                font-family: 'Pinyon Script';
                font-style: normal;
                font-weight: 400;
                src: local('Pinyon Script'), local('PinyonScript'), url(data:font/woff2;base64,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) format('woff2');
                unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }
            input:focus,textarea:focus {outline: none;}
            div,textarea,video{border-radius:15px}            
            body{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:14px;line-height:1em;background-color:#f7f7f9;color:#000}
            a{color:#24292e;cursor:pointer;text-decoration:none}
            a:hover{color:#24292e}
            .title{text-align:center;margin-top:2rem;letter-spacing:2px;margin-bottom:2rem}
            .title a{color:#333;text-decoration:none;font-family:"Pinyon Script",cursive;font-size:150%}
            .list-wrapper{width:80%;margin:0 auto 40px;position:relative;box-shadow:0 0 32px 0 rgba(0,0,0,.1)}
            .list-container{position:relative;overflow:hidden}
            .list-header-container{position:relative}
            .list-header-container a.back-link{color:#000;display:inline-block;position:absolute;font-size:16px;margin:20px 10px;padding:10px 10px;vertical-align:middle;text-decoration:none}
            .list-container,.list-header-container,.list-wrapper,a.back-link:hover,body{color:#24292e}
            .list-header-container .table-header{line-height:1.3em;margin:0;border:0 none;padding:30px 60px;text-align:left;font-weight:400;color:#000;background-color:#f7f7f9}
            .login{display: inline-table;position: absolute;font-size:16px;padding:30px 20px;vertical-align:middle;right:0px;top:0px}
            .list-body-container{position:relative;left:0;overflow-x:hidden;overflow-y:auto;box-sizing:border-box;background:#fff}
            .list-table{width:100%;padding:10px;border-spacing:0}
            .list-table tr{height:40px}
            .list-table tr[data-to]:hover{background:#f1f1f1}
            .list-table tr:first-child{background:#fff}
            .list-table td,.list-table th{text-overflow:ellipsis;text-align:left;padding: 0 5px;white-space:nowrap;overflow:hidden;max-width:5em;}
            .list-table .size,.list-table .updated_at{text-align:right}
            .list-table .file ion-icon{font-size:15px;margin-right:0px;vertical-align:bottom}
            .list-table .size{border-radius:0px 25px 25px 0px}
            .list-table .file{border-radius:25px 0px 0px 25px}
<?php if ($config['admin']) { ?>
            .operate{display: inline-table;margin:0;list-style:none;}
            .operate ul{position: absolute;display: none;background: #fff;border:1px #f7f7f7 solid;border-radius: 5px;margin:-17px 0 0 0;padding: 0;color:#205D67;}
            .operate:hover ul{position: absolute;display:inline-table;}
            .operate ul li{padding:1px;list-style:none;}
            .operatediv_close{position: absolute;right: 3px;top:3px;}
<?php } ?>
            .readme{padding:10px;background-color: #fff;}
            #readme{padding:.5em 0;text-align: left}

            @media only screen and (max-width:480px){
                body{
                    margin:5px;
                }
                .title{margin-bottom: 24px;font-size:1.5em;}
                .list-wrapper{width:100%; margin-bottom:24px;}
                .list-table {padding: 1em 0;}
                .list-table .updated_at{display:none}
            }

            .password {
                outline: none;
                border: 0;
                border-radius:1em;
                line-height:1.6em;
                padding-left:1em;
                background:aliceblue;
            }

            .submit {
                outline:none;
                border:0;
                border-radius:1em;
            }
        </style>
    </head>

    <body>
    <h1 class="title">
        <a href="<?php echo $config['base_path']; ?>"><?php echo $config['sitename'] ;?></a>
    </h1>
    
    <div class="list-wrapper">
        <div class="list-container">
            <div class="list-header-container">
<?php if ($path !== '/') {
                    $current_url = $_SERVER['PHP_SELF'];
                    while (substr($current_url, -1) === '/') {
                        $current_url = substr($current_url, 0, -1);
                    }
                    if (strpos($current_url, '/') !== FALSE) {
                        $parent_url = substr($current_url, 0, strrpos($current_url, '/'));
                    } else {
                        $parent_url = $current_url;
                    }
                    ?>
                <a href="<?php echo path_format($parent_url); ?>" class="back-link">
                    <ion-icon name="arrow-back"></ion-icon>
                </a>
<?php } ?>
                <h3 class="table-header"><?php echo str_replace('%23', '#', str_replace('&','&amp;', $path)); ?></h3>
                <div class="login">
<?php if (getenv('admin')!='') if (!$config['admin']) {?>
                    <a onclick="login();">登录</a>
<?php } else { ?>
                        <li class="operate">管理<ul style="margin:-17px 0 0 -66px;">
                        <li><a onclick="logout()">登出</a></li>
                        <?php if (isset($files['folder'])) { ?>
                        <li><a onclick="showdiv(event,'create','');">新建</a></li>
                        <li><a onclick="showdiv(event,'encrypt','');">加密</a></li>
                        </ul></li>
<?php } 
                    } ?>
                </div>
            </div>
            <div class="list-body-container">
<?php if (path_format('/'.path_format(urldecode($config['list_path'].$path)).'/')==path_format('/'.path_format($config['imgup_path']).'/')&&$config['imgup_path']!=''&&!$config['admin']) { ?>
                <div id="upload_div" style="margin:10px"><center>
        <form action="" method="POST">
        <input id="upload_content" type="hidden" name="guest_upload_filecontent">
        <input id="upload_file" type="file" name="upload_filename" onchange="base64upfile()">
        <button type=submit>上传</button>
        文件大小<4M，不然传输失败！
        </form><center>
    </div>
<?php } else { 
                //if ($config['admin'] or $ishidden<4) {
                if ($config['ishidden']<4) {
                if (isset($files['file'])) {
                    ?>
                    <div style="margin: 12px 4px 4px; text-align: center">
                    	<div style="margin: 24px">
                            <textarea id="url" title="url" rows="1" style="width: 100%; margin-top: 2px;" readonly><?php echo str_replace('%26amp%3B','&amp;',spurlencode(path_format($config['base_path'] . '/' . $path), '/')); ?></textarea>
                            <a href="<?php echo path_format($config['base_path'] . '/' . $path);//$files['@microsoft.graph.downloadUrl'] ?>"><ion-icon name="download" style="line-height: 16px;vertical-align: middle;"></ion-icon>&nbsp;下载</a>
                        </div>
                        <div style="margin: 24px">
<?php
                        $ext = strtolower(substr($path, strrpos($path, '.') + 1));
                        $DPvideo='';
                        if (in_array($ext, ['ico', 'bmp', 'gif', 'jpg', 'jpeg', 'jpe', 'jfif', 'tif', 'tiff', 'png', 'heic', 'webp'])) {
                            echo '
                        <img src="' . $files['@microsoft.graph.downloadUrl'] . '" alt="' . substr($path, strrpos($path, '/')) . '" onload="if(this.offsetWidth>document.getElementById(\'url\').offsetWidth) this.style.width=\'100%\';" />
                        ';
                        } elseif (in_array($ext, ['mp4', 'webm', 'mkv', 'flv', 'blv', 'avi', 'wmv', 'ogg'])) {
                            //echo '<video src="' . $files['@microsoft.graph.downloadUrl'] . '" controls="controls" style="width: 100%"></video>';
                            $DPvideo=$files['@microsoft.graph.downloadUrl'];
                            echo '<div id="video-a0"></div>';
                        } elseif (in_array($ext, ['mp3', 'wma', 'flac', 'wav'])) {
                            echo '
                        <audio src="' . $files['@microsoft.graph.downloadUrl'] . '" controls="controls" style="width: 100%"></audio>
                        ';
                        } elseif (in_array($ext, ['pdf'])) {
                            echo '
                        <embed src="' . $files['@microsoft.graph.downloadUrl'] . '" type="application/pdf" width="100%" height=800px">
                        ';
                        } elseif (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
                            echo '
                        <iframe id="office-a" src="https://view.officeapps.live.com/op/view.aspx?src=' . urlencode($files['@microsoft.graph.downloadUrl']) . '" style="width: 100%;height: 800px" frameborder="0"></iframe>
                        ';
                        } elseif (in_array($ext, ['txt', 'sh', 'php', 'asp', 'js', 'html'])) {
                            /*if ($files['name']==='当前demo的index.php') {
                                $txtstr = '<!--修改时间：' . date("Y-m-d H:i:s",filectime(__DIR__.'/index.php')) . '-->
';
                                $txtstr .= htmlspecialchars(file_get_contents(__DIR__.'/index.php'));
                            } else {*/
                                $txtstr = htmlspecialchars(curl_request($files['@microsoft.graph.downloadUrl']));
                            //} ?>
                        <div id="txt">
                        <?php if ($config['admin']) { ?><form id="txt-form" action="" method="POST">
                            <a onclick="enableedit(this);" id="txt-editbutton">点击后编辑</a>
                            <a id="txt-save" style="display:none">保存</a>
                         <?php } ?>
                            <textarea id="txt-a" name="editfile" readonly style="width: 100%; margin-top: 2px;" <?php if ($config['admin']) echo 'onchange="document.getElementById(\'txt-save\').onclick=function(){document.getElementById(\'txt-form\').submit();}"';?> ><?php echo $txtstr;?></textarea>
                        <?php if ($config['admin']) echo '</form>';?>
                        </div>
                        <?php } elseif (in_array($ext, ['md'])) {
                            echo '
                        <div class="markdown-body" id="readme"><textarea id="readme-md" style="display:none;">' . curl_request($files['@microsoft.graph.downloadUrl']) . '</textarea></div>
                        ';
                        } else {
                            echo '<span>文件格式不支持预览</span>';
                        } ?>
                        </div>
                    </div>
<?php } else { ?>
                    <table class="list-table" id="list-table">
                        <tr id="tr0">
                            <!--<th class="updated_at" width="5%">序号</th>-->
                            <th class="file" width="60%">文件</th>
                            <th class="updated_at" width="25%">修改时间</th>
                            <th class="size" width="15%">大小</th>
                        </tr>
                        <!-- Dirs -->
<?php
                        $filenum = $_POST['filenum'];
                        if (!$filenum and $files['folder']['page']) $filenum = ($files['folder']['page']-1)*200;
                        $readme = false;
                        if (isset($files['error'])) {
                            echo '<tr><td colspan="3">' . $files['error']['message'] . '<td></tr>';
                            $statusCode=404;
                        } else {
                            #echo json_encode($files['children'], JSON_PRETTY_PRINT);
                            foreach ($files['children'] as $file) {
                                // Folders
                                if (isset($file['folder'])) { 
                                    $filenum++; ?>
                                    <tr data-to id="tr<?php echo $filenum;?>">
                                        <!--<td class="updated_at"><?php echo $filenum;?></td>-->
                                        <td class="file">
                                            <ion-icon name="folder"></ion-icon>
                                            <a id="file_a<?php echo $filenum;?>" href="<?php echo path_format($config['base_path'] . '/' . $path . '/' . encode_str_replace($file['name']) . '/'); ?>">
                                                <?php echo str_replace('&','&amp;', $file['name']); ?>
                                            </a>
<?php                                       if ($config['admin']) {?>&nbsp;&nbsp;&nbsp;
                                            <li class="operate">管理<ul>
                                                <li><a onclick="showdiv(event,'encrypt',<?php echo $filenum;?>);">加密</a></li>
                                                <li><a onclick="showdiv(event, 'rename',<?php echo $filenum;?>);">重命名</a></li>
                                                <li><a onclick="showdiv(event, 'move',<?php echo $filenum;?>);">移动</a></li>
                                                <li><a onclick="showdiv(event, 'delete',<?php echo $filenum;?>);">删除</a></li>
                                            </ul></li>
<?php                                       }?>
                                        </td>
                                        <td class="updated_at"><?php echo time_format($file['lastModifiedDateTime']); ?></td>
                                        <td class="size"><?php echo size_format($file['size']); ?></td>
                                    </tr>
<?php                               }
                            }
                            foreach ($files['children'] as $file) {
                                // Files
                                if (isset($file['file'])) {
                                    if ($config['admin'] or (substr($file['name'],0,1) !== '.' and $file['name'] !== $config['passfile']) ) {
                                    if (strtolower($file['name']) === 'readme.md') $readme = $file;
                                    if (strtolower($file['name']) === 'index.html') {
                                        $html = curl_request(fetch_files(spurlencode(path_format($path . '/' .$file['name']),'/'))['@microsoft.graph.downloadUrl']);
                                        return output($html,200);
                                    }
                                    $filenum++; ?>
                                    <tr data-to id="tr<?php echo $filenum;?>">
                                        <!--<td class="updated_at"><?php echo $filenum;?></td>-->
                                        <td class="file">
                                            <ion-icon name="document"></ion-icon>
                                            <a id="file_a<?php echo $filenum;?>" href="<?php echo path_format($config['base_path'] . '/' . $path . '/' . encode_str_replace($file['name'])); ?>?preview" target=_blank>
                                                <?php echo str_replace('&','&amp;', $file['name']); ?>
                                            </a>
                                            <a href="<?php echo path_format($config['base_path'] . '/' . $path . '/' . str_replace('&','&amp;', $file['name']));?>">
                                                <ion-icon name="download"></ion-icon>
                                            </a>
                                            <?php if ($config['admin']) {?>&nbsp;&nbsp;&nbsp;
                                            <li class="operate">管理<ul>
                                                <li><a onclick="showdiv(event, 'rename',<?php echo $filenum;?>);">重命名</a></li>
                                                <li><a onclick="showdiv(event, 'move',<?php echo $filenum;?>);">移动</a></li>
                                                <li><a onclick="showdiv(event, 'delete',<?php echo $filenum;?>);">删除</a></li>
                                            </ul></li>
                                            <?php }?>
                                        </td>
                                        <td class="updated_at"><?php echo time_format($file['lastModifiedDateTime']); ?></td>
                                        <td class="size"><?php echo size_format($file['size']); ?></td>
                                    </tr>
                                <?php }
                                }
                            }
                        } ?>
                    </table>
                    <?php
                    if ($files['folder']['childCount']>200) {
                        //echo json_encode($files['folder'], JSON_PRETTY_PRINT);
                        $pagenum = $files['folder']['page'];
                        $maxpage = ceil($files['folder']['childCount']/200);
                        $prepagenext = '<form action="" method="POST" id="nextpageform">
                        <input type="hidden" id="pagenum" name="pagenum" value="'. $pagenum .'">
                        <table width=100% border=0>
                            <tr>
                                <td width=60px align=center>';
                        //if (isset($_POST['nextlink'])) $prepagenext .= '<a href="javascript:history.back(-1)">上一页</a>';
                        if ($pagenum!=1) {
                            $prepagenum = $pagenum-1;
                            $prepagenext .= '
                            <a onclick="nextpage('.$prepagenum.');">上一页</a>
                            ';
                        }
                        $prepagenext .= '</td>
                                <td class="updated_at">
                                ';
                        //$pathpage = path_format($config['list_path'].$path).'_'.$page;
                        for ($page=1;$page<=$maxpage;$page++) {
                            /*if ($files['folder'][path_format($config['list_path'].$path).'_'.$page]) $prepagenext .= '  <input type="hidden" name="'.$path.'_'.$page.'" value="'.$files['folder'][path_format($config['list_path'].$path).'_'.$page].'">
                                    ';*/
                            if ($page == $pagenum) {
                                $prepagenext .= '<font color=red>' . $page . '</font> 
                                ';
                            } else {
                                $prepagenext .= '<a onclick="nextpage('.$page.');">' . $page . '</a> 
                                ';
                            }
                        }
                        $prepagenext = substr($prepagenext,0,-1);
                        $prepagenext .= '</td>
                                <td width=60px align=center>';
                        if ($pagenum!=$maxpage) {
                            $nextpagenum = $pagenum+1;
                            $prepagenext .= '
                            <a onclick="nextpage('.$nextpagenum.');">下一页</a>
                            ';
                        }
                            $prepagenext .= '</td>
                            </tr></table>
                            </form>';
                            echo $prepagenext;
                    }
                    //<script src="//cdn.staticfile.org/jquery/1.10.2/jquery.min.js"></script>
                    if ($config['admin']) { ?>
    <div id="upload_div" style="margin:0 0 16px 0"><center>
        <input id="upload_file" type="file" name="upload_filename" multiple="multiple">
        <button id="upload_submit" onclick="preup();">上传</button>
        </center>
    </div>
    <?php }
                    if ($readme) {
                        echo '</div></div></div><div class="list-wrapper"><div class="list-container"><div class="list-header-container"><div class="readme">
<svg class="octicon octicon-book" viewBox="0 0 16 16" version="1.1" width="16" height="16" aria-hidden="true"><path fill-rule="evenodd" d="M3 5h4v1H3V5zm0 3h4V7H3v1zm0 2h4V9H3v1zm11-5h-4v1h4V5zm0 2h-4v1h4V7zm0 2h-4v1h4V9zm2-6v9c0 .55-.45 1-1 1H9.5l-1 1-1-1H2c-.55 0-1-.45-1-1V3c0-.55.45-1 1-1h5.5l1 1 1-1H15c.55 0 1 .45 1 1zm-8 .5L7.5 3H2v9h6V3.5zm7-.5H9.5l-.5.5V12h6V3z"></path></svg>
<span style="line-height: 16px;vertical-align: top;">'.$readme['name'].'</span>
<div class="markdown-body" id="readme"><textarea id="readme-md" style="display:none;">' . curl_request(fetch_files(spurlencode(path_format($path . '/' .$readme['name']),'/'))['@microsoft.graph.downloadUrl'])
                            . '</textarea></div></div>';
                    }
                }
                } else {
                    echo '
<div style="padding:20px">
	<center><h4>输入密码进行查看</h4>
	  <form action="" method="post">
		    <label>密码</label>
		    <input class="password" name="password1" type="password"/>
		    <button class="submit" type="submit">查看</button>
	  </form>
    </center>
</div>';
                    $statusCode = 401;
                } }
                ?>
            </div>
        </div>
    </div>
    <div id="mask" style="border-radius:0;position:absolute;display:none;left:0px;top:0px;width:100%;background-color:#000;filter:alpha(opacity=50);opacity:0.5"></div>
    <?php if ($config['admin']) { ?>
<div>    
    <div id="rename_div" name="operatediv" style="position: absolute;border: 10px #CCCCCC;background-color: #FFFFCC; display:none">
        <div style="margin:16px">
        <label id="rename_label"></label><br><br><a onclick="operatediv_close('rename')" class="operatediv_close">关闭</a>
        <form id="rename_form" onsubmit="return submit_operate('rename');">
        <input id="rename_sid" name="rename_sid" type="hidden" value="">
            <input id="rename_hidden" name="rename_oldname" type="hidden" value="">
            <input id="rename_input" name="rename_newname" type="text" value="">
            <input name="operate_action" type="submit" value="重命名">
        </form>
        </div>
    </div>
    <div id="delete_div" name="operatediv" style="position: absolute;border: 10px #CCCCCC;background-color: #FFFFCC; display:none">
        <div style="margin:16px">
        <br><a onclick="operatediv_close('delete')" class="operatediv_close">关闭</a>
        <label id="delete_label"></label>
        <form id="delete_form" onsubmit="return submit_operate('delete');">
            <label id="delete_input"></label>
        <input id="delete_sid" name="delete_sid" type="hidden" value="">
            <input id="delete_hidden" name="delete_name" type="hidden" value="">
            <input name="operate_action" type="submit" value="确定删除">
        </form>
        </div>
    </div>
    <div id="encrypt_div" name="operatediv" style="position: absolute;border: 10px #CCCCCC;background-color: #FFFFCC; display:none">
        <div style="margin:16px">
        <label id="encrypt_label"></label><br><br><a onclick="operatediv_close('encrypt')" class="operatediv_close">关闭</a>
        <form id="encrypt_form" onsubmit="return submit_operate('encrypt');">
        <input id="encrypt_sid" name="encrypt_sid" type="hidden" value="">
            <input id="encrypt_hidden" name="encrypt_folder" type="hidden" value="">
            <input id="encrypt_input" name="encrypt_newpass" type="text" value="" placeholder="输入想要设置的密码">
            <?php if (getenv('passfile')!='') {?><input name="operate_action" type="submit" value="加密"><?php } else { ?>
            <br><label>先在环境变量设置passfile才能加密</label><?php } ?>
        </form>
        </div>
    </div>
    <div id="move_div" name="operatediv" style="position: absolute;border: 10px #CCCCCC;background-color: #FFFFCC; display:none">
        <div style="margin:16px">
        <label id="move_label"></label><br><br><a onclick="operatediv_close('move')" class="operatediv_close">关闭</a>
        <form id="move_form" onsubmit="return submit_operate('move');">
        <input id="move_sid" name="move_sid" type="hidden" value="">
            <input id="move_hidden" name="move_name" type="hidden" value="">
            <select id="move_input" name="move_folder">
<?php if ($path != '/') { ?>
                <option value="/../">上一级目录</option>
<?php }
if (isset($files['children'])) foreach ($files['children'] as $file) {
                if (isset($file['folder'])) { ?>
                <option value="<?php echo str_replace('&','&amp;', $file['name']);?>"><?php echo str_replace('&','&amp;', $file['name']);?></option>
<?php }
            } ?>
            </select>
            <input name="operate_action" type="submit" value="移动">
        </form>
        </div>
    </div>
    <div id="create_div" name="operatediv" style="position: absolute;border: 1px #CCCCCC;background-color: #FFFFCC; display:none">
        <div style="margin:50px">
        <label id="create_label"></label><br><a onclick="operatediv_close('create')" class="operatediv_close">关闭</a>
        <form id="create_form" onsubmit="return submit_operate('create');">
            <input id="create_sid" name="create_sid" type="hidden" value="">
                <input id="create_hidden" type="hidden" value="">
                　　　<input id="create_type_folder" name="create_type" type="radio" value="folder" onclick="document.getElementById('create_text_div').style.display='none';">文件夹
                <input id="create_type_file" name="create_type" type="radio" value="file" onclick="document.getElementById('create_text_div').style.display='';" checked>文件<br>
                名字：<input id="create_input" name="create_name" type="text" value=""><br>
                <div id="create_text_div">内容：<textarea id="create_text" name="create_text" rows="6" cols="40"></textarea><br></div>
                　　　<input name="operate_action" type="submit" value="新建">
        </form>
        </div>
    </div>
</div>
    <?php } else {
        if (getenv('admin')!='') { ?>
        <div id="login_div" style="border-radius:1em;position:absolute;background-color:white;display:none;top:25%;width:25em;left:calc((100% - 25em) / 2);height:8em">
            <a onclick="operatediv_close('login')" style="position: absolute;right: 15px;top:15px;">关闭</a>
	  <center><h4>输入管理密码</h4>
	  <form action="<?php echo $_GET['preview']?'?preview&':'?';?>admin" method="post">
		    <label>密码</label>
            <input id="login_input" class="password" name="password1" type="password"/>
		    <button class="submit" type="submit">查看</button>
	  </form>
      </center>
	</div>
    <?php }
    } ?>
    <center><font id="date" color="black"><?php $weekarray=array("日","一","二","三","四","五","六"); echo date("Y-m-d H:i:s")." 星期".$weekarray[date("w")]." ".$config['sourceIp'];?></font></center>
    <script>
        setInterval(() => {
            const addZero = (num) => num < 10 ? '0' + num : num;
            const Week = (week) => {
                switch (week) {
                    case 0:
                        return '日';
                    case 1:
                        return '一';
                    case 2:
                        return '二';
                    case 3:
                        return '三';
                    case 4:
                        return '四';
                    case 5:
                        return '五';
                    case 6:
                        return '六';
                    default:
                        return week;
                }
            };

            const date = new Date();

            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const day = date.getDate();

            const hours = date.getHours();
            const minutes = date.getMinutes();
            const seconds = date.getSeconds();

            const week = date.getDay();

            const element = document.querySelector('#date');
            const ip = element.innerHTML.split(' ').pop();

            let format = 'yyyy-mm-dd hh:MM:ss 星期w';
            element.innerHTML = format
                .replace('yyyy', year)
                .replace('mm', addZero(month))
                .replace('dd', addZero(day))
                .replace('hh', addZero(hours))
                .replace('MM', addZero(minutes))
                .replace('ss', addZero(seconds))
                .replace('w', Week(week))
                + ' ' + ip;
        }, 1000);
    </script>

    <script>
        const TBODY = document.querySelector('#list-table').querySelector('tbody');
        const TRS = TBODY.querySelectorAll('tr');
        let readme = null;
        TRS.forEach(TR => {
            const A = TR.querySelector('a');
            if (A && A.href.indexOf('README.md') >= 0) {
                readme = TR;
                return;
            }
        });
        if (readme) {
            TBODY.removeChild(readme);
        }
    </script>
    </body>
    <link rel="stylesheet" href="//s0.pstatp.com/cdn/expire-1-M/github-markdown-css/3.0.1/github-markdown.min.css">
    <script type="text/javascript" src="//s0.pstatp.com/cdn/expire-1-M/marked/0.6.2/marked.min.js"></script>
    <!-- <link rel="stylesheet" href="//unpkg.zhimg.com/github-markdown-css@3.0.1/github-markdown.css">
    <script type="text/javascript" src="//unpkg.zhimg.com/marked@0.6.2/marked.min.js"></script> -->
    <script type="text/javascript">
        var root = '<?php echo $config["base_path"]; ?>';
        function path_format(path) {
            path = '/' + path + '/';
            while (path.indexOf('//') !== -1) {
                path = path.replace('//', '/')
            }
            //if (path.substr(-1)=='/') path = path.substr(0,path.length-1);
            return path
        }
        document.querySelectorAll('.table-header').forEach(function (e) {
            var path = e.innerText;
            var paths = path.split('/');
            if (paths <= 2)
                return;
            e.innerHTML = '/ ';
            for (var i = 1; i < paths.length - 1; i++) {
                var to = path_format(root + paths.slice(0, i + 1).join('/'));
                e.innerHTML += '<a href="' + to + '">' + paths[i] + '</a> / '
            }
            e.innerHTML += paths[paths.length - 1];
            e.innerHTML = e.innerHTML.replace(/\s\/\s$/, '')
        });
        var $readme = document.getElementById('readme');
        if ($readme) {
            $readme.innerHTML = marked(document.getElementById('readme-md').innerText)
        }
<?php if ($_POST['password1']!='') { //有密码写目录密码 ?>
        var $ishidden = '<?php echo $config['ishidden']; ?>';
        var $hiddenpass = '<?php echo md5($_POST['password1']);?>';
        if ($ishidden==2) {
            var expd = new Date();
            expd.setTime(expd.getTime()+(12*60*60*1000));
            var expires = "expires="+expd.toGMTString();
            document.cookie="password="+$hiddenpass+";"+expires;
        }
<?php }
if ($_COOKIE['timezone']=='') { //无时区写时区 ?>
        var nowtime= new Date();
        var timezone = 0-nowtime.getTimezoneOffset()/60;
        var expd = new Date();
        expd.setTime(expd.getTime()+(2*60*60*1000));
        var expires = "expires="+expd.toGMTString();
        document.cookie="timezone="+timezone+";"+expires;
        if (timezone!='8') {
            alert('Your timezone is '+timezone+', reload local timezone.');
            location.href=location.protocol + "//" + location.host + "<?php echo path_format($config['base_path'] . '/' . $path );?>" ;
        }
<?php }
if ($files['folder']['childCount']>200) { //有下一页 ?>
        function nextpage(num) {
            document.getElementById('pagenum').value=num;
            document.getElementById('nextpageform').submit();
        }
<?php }
if ($_GET['preview']) { //在预览时处理 ?>
        var $url = document.getElementById('url');
        if ($url) {
            $url.innerHTML = location.protocol + '//' + location.host + $url.innerHTML;
            $url.style.height = $url.scrollHeight + 'px';
        }
        var $officearea=document.getElementById('office-a');
        if ($officearea) {
            $officearea.style.height = window.innerHeight + 'px';
        }
        var $textarea=document.getElementById('txt-a');
        if ($textarea) {
            $textarea.style.height = $textarea.scrollHeight + 'px';
        }
<?php if (!!$DPvideo) { ?>
        function loadResources(type, src, callback) {
                let script = document.createElement(type);
                let loaded = false;
                if (typeof callback === 'function') {
                    script.onload = script.onreadystatechange = () => {
                        if (!loaded && (!script.readyState || /loaded|complete/.test(script.readyState))) {
                            script.onload = script.onreadystatechange = null;
                            loaded = true;
                            callback();
                        }
                    }
                }
                if (type === 'link') {
                    script.href = src;
                    script.rel = 'stylesheet';
                } else {
                    script.src = src;
                }
                document.getElementsByTagName('head')[0].appendChild(script);
            }
            function addVideos(videos) {
                let host = 'https://s0.pstatp.com/cdn/expire-1-M';
                let unloadedResourceCount = 4;
                let callback = (() => {
                    return () => {
                        if (!--unloadedResourceCount) {
                            createDplayers(videos);
                        }
                    };
                })(unloadedResourceCount, videos);
                loadResources(
                    'link',
                    host + '/dplayer/1.25.0/DPlayer.min.css',
                    callback
                );
                loadResources(
                    'script',
                    host + '/dplayer/1.25.0/DPlayer.min.js',
                    callback
                );
                loadResources(
                    'script',
                    host + '/hls.js/0.12.4/hls.light.min.js',
                    callback
                );
                loadResources(
                    'script',
                    host + '/flv.js/1.5.0/flv.min.js',
                    callback
                );
            }
            function createDplayers(videos) {
                for (i = 0; i < videos.length; i++) {
                    console.log(videos[i]);
                    new DPlayer({
                        container: document.getElementById('video-a' + i),
                        screenshot: true,
                        video: {
                            url: videos[i]
                        }
                    });
                }
            }
        addVideos(['<?php echo $DPvideo;?>']);
<?php } 
}
if (getenv('admin')!='') { //有登录或操作，需要关闭DIV时 ?>
        function operatediv_close(operate)
        {
            document.getElementById(operate+'_div').style.display='none';
            document.getElementById('mask').style.display='none';
        }
<?php }
if (path_format('/'.path_format(urldecode($config['list_path'].$path)).'/')==path_format('/'.path_format($config['imgup_path']).'/')&&$config['imgup_path']!=''&&!$config['admin']) { //当前是图床目录时 ?>
            function base64upfile() {
                var $file=document.getElementById('upload_file').files[0];
                var $reader = new FileReader();
                $reader.onloadend=function(e) {
                    var $data=$reader.result;
                    document.getElementById('upload_content').value=$data;
                }
                $reader.readAsDataURL($file);
            }
<?php }
if ($config['admin']) { //管理登录后 ?>
        function logout() {
            var expd = new Date();
            expd.setTime(expd.getTime()-(60*1000));
            var expires = "expires="+expd.toGMTString();
            document.cookie="<?php echo $config['function_name'];?>='';"+expires;
            location.href=location.protocol + "//" + location.host + "<?php echo path_format($config['base_path'].str_replace('&amp;','&',$path));?>";
        }
        function showdiv(event,action,num) {
            var $operatediv=document.getElementsByName('operatediv');
            for ($i=0;$i<$operatediv.length;$i++) {
                $operatediv[$i].style.display='none';
            }
            document.getElementById('mask').style.display='';
            const max=(a,b)=>a>b?a:b;
            document.getElementById('mask').style.height=max(window.innerHeight,document.scrollingElement.scrollHeight)+'px';            if (num=='') {
            var str='';
            } else {
                var str=document.getElementById('file_a'+num).innerText;
                if (str.substr(-1)==' ') str=str.substr(0,str.length-1);
            }
            document.getElementById(action + '_div').style.display='';
            document.getElementById(action + '_label').innerText=str;//.replace(/&/,'&amp;');
            document.getElementById(action + '_sid').value=num;
            document.getElementById(action + '_hidden').value=str;
            if (action=='rename') document.getElementById(action + '_input').value=str;

            var $e = event || window.event;
            var $scrollX = document.documentElement.scrollLeft || document.body.scrollLeft;
            var $scrollY = document.documentElement.scrollTop || document.body.scrollTop;
            var $x = $e.pageX || $e.clientX + $scrollX;
            var $y = $e.pageY || $e.clientY + $scrollY;
            if (action=='create') {
                document.getElementById(action + '_div').style.left=(document.body.clientWidth-document.getElementById(action + '_div').offsetWidth)/2 +'px';
                document.getElementById(action + '_div').style.top=(window.innerHeight-document.getElementById(action + '_div').offsetHeight)/2+$scrollY +'px';
            } else {
                if ($x + document.getElementById(action + '_div').offsetWidth > document.body.clientWidth) {
                    document.getElementById(action + '_div').style.left=document.body.clientWidth-document.getElementById(action + '_div').offsetWidth+'px';
                } else {
                    document.getElementById(action + '_div').style.left=$x+'px';
                }
                document.getElementById(action + '_div').style.top=$y+'px';
            }
            document.getElementById(action + '_input').focus();
        }
        function enableedit(obj)
        {
            document.getElementById('txt-a').readOnly=!document.getElementById('txt-a').readOnly;
            //document.getElementById('txt-editbutton').innerHTML=(document.getElementById('txt-editbutton').innerHTML=='取消编辑')?'点击后编辑':'取消编辑';
            obj.innerHTML=(obj.innerHTML=='取消编辑')?'点击后编辑':'取消编辑';
            document.getElementById('txt-save').style.display=document.getElementById('txt-save').style.display==''?'none':'';
        }
        function submit_operate(str)
        {
            var num=document.getElementById(str+'_sid').value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", '', true);
            xhr.setRequestHeader('x-requested-with','XMLHttpRequest');
            xhr.send(serializeForm(str+'_form'));
            xhr.onload = function(e){
                var html;
                if (xhr.status<300) {
                    if (str=='rename') {
                        //if (xhr.status==200) 
                        html=JSON.parse(xhr.responseText);
                        var file_a = document.getElementById('file_a'+num);
                        file_a.innerText=html.name;
                        file_a.href = (file_a.href.substr(-8)=='?preview')?(html.name+'?preview'):(html.name+'/');
                    }
                    if (str=='move'||str=='delete') document.getElementById('tr'+num).parentNode.removeChild(document.getElementById('tr'+num));
                    if (str=='create') {
                        html=JSON.parse(xhr.responseText);
                        addelement(html);
                    }
                } else alert(xhr.status+'\n'+xhr.responseText);
                    //var $operatediv=document.getElementsByName('operatediv');
                    //for ($i=0;$i<$operatediv.length;$i++) {
                    //    $operatediv[$i].style.display='none';
                    //}
                document.getElementById(str+'_div').style.display='none';
                document.getElementById('mask').style.display='none';
            }
            return false;
        }
        function addelement(html) {
            var tr1=document.createElement('tr');
            tr1.setAttribute('data-to',1);
            var td1=document.createElement('td');
            td1.setAttribute('class','file');
            var a1=document.createElement('a');
            a1.href=html.name.replace(/#/,'%23');
            a1.innerText=html.name;
            a1.target='_blank';
            var td2=document.createElement('td');
            td2.setAttribute('class','updated_at');
            td2.innerText=html.lastModifiedDateTime;
            var td3=document.createElement('td');
            td3.setAttribute('class','size');
            td3.innerText=size_format(html.size);
            if (!!html.folder) {
                a1.href+='/';
                document.getElementById('tr0').parentNode.insertBefore(tr1,document.getElementById('tr0').nextSibling);
            }
            if (!!html.file) {
                a1.href+='?preview';
                document.getElementById('tr0').parentNode.appendChild(tr1);
            }
            tr1.appendChild(td1);
            td1.appendChild(a1);
            tr1.appendChild(td2);
            tr1.appendChild(td3);
        }
        //获取指定form中的所有的<input>对象 
function getElements(formId) { 
  var form = document.getElementById(formId); 
  var elements = new Array(); 
  var tagElements = form.getElementsByTagName('input'); 
  for (var j = 0; j < tagElements.length; j++){ 
    elements.push(tagElements[j]); 
  } 
  var tagElements = form.getElementsByTagName('select'); 
  for (var j = 0; j < tagElements.length; j++){ 
    elements.push(tagElements[j]); 
  } 
  var tagElements = form.getElementsByTagName('textarea'); 
  for (var j = 0; j < tagElements.length; j++){ 
    elements.push(tagElements[j]); 
  }
  return elements; 
} 
//组合URL 
function serializeElement(element) { 
  var method = element.tagName.toLowerCase(); 
  var parameter; 
  if(method == 'select'){
    parameter = [element.name, element.value]; 
  }
  switch (element.type.toLowerCase()) { 
    case 'submit': 
    case 'hidden': 
    case 'password': 
    case 'text':
    case 'date':
    case 'textarea': 
       parameter = [element.name, element.value];
       break;
    case 'checkbox': 
    case 'radio': 
      if (element.checked){
        parameter = [element.name, element.value]; 
      }
      break;    
  } 
  if (parameter) { 
    var key = encodeURIComponent(parameter[0]); 
    if (key.length == 0) 
      return; 
    if (parameter[1].constructor != Array) 
      parameter[1] = [parameter[1]]; 
    var values = parameter[1]; 
    var results = []; 
    for (var i = 0; i < values.length; i++) { 
      results.push(key + '=' + encodeURIComponent(values[i])); 
    } 
    return results.join('&'); 
  } 
} 
//调用方法  
function serializeForm(formId) { 
  var elements = getElements(formId); 
  var queryComponents = new Array(); 
  for (var i = 0; i < elements.length; i++) { 
    var queryComponent = serializeElement(elements[i]); 
    if (queryComponent) {
      queryComponents.push(queryComponent); 
    } 
  } 
  return queryComponents.join('&'); 
} 
            function uploadbuttonhide()
            {
                document.getElementById('upload_submit').disabled='disabled';
                document.getElementById('upload_file').disabled='disabled';
                //$("#upload_submit").hide();
                //$("#upload_file").hide();
                document.getElementById('upload_submit').style.display='none';
                document.getElementById('upload_file').style.display='none';
            }
            function uploadbuttonshow()
            {
                document.getElementById('upload_file').disabled='';
                document.getElementById('upload_submit').disabled='';
                //$("#upload_submit").show();
                //$("#upload_file").show();
                document.getElementById('upload_submit').style.display='';
                document.getElementById('upload_file').style.display='';
            }
            function preup()
            {
                uploadbuttonhide();
                var files=document.getElementById('upload_file').files;
                var table1=document.createElement('table');
                document.getElementById('upload_div').appendChild(table1);
                table1.setAttribute('class','list-table');
                var timea=new Date().getTime();
                var i=0;
                getuplink(i);
                function getuplink(i) {
                    var file=files[i];
                    var tr1=document.createElement('tr');
                    table1.appendChild(tr1);
                    tr1.setAttribute('data-to',1);
                    var td1=document.createElement('td');
                    tr1.appendChild(td1);
                    td1.setAttribute('style','width:30%');
                    td1.setAttribute('id','upfile_td1_'+timea+'_'+i);
                    td1.innerHTML=file.name+'<br>'+size_format(file.size);
                    var td2=document.createElement('td');
                    tr1.appendChild(td2);
                    td2.setAttribute('id','upfile_td2_'+timea+'_'+i);
                    td2.innerHTML='获取链接 ...';
                if (file.size>15*1024*1024*1024) {
                    td2.innerHTML='<font color="red">大于15G，终止上传。</font>';
                    uploadbuttonshow();
                    return;
                }
                var xhr1 = new XMLHttpRequest();
                xhr1.open("POST", '');
                xhr1.setRequestHeader('x-requested-with','XMLHttpRequest');
                xhr1.send('upbigfilename='+ encodeURIComponent(file.name) +'&filesize='+ file.size +'&lastModified='+ file.lastModified);
                xhr1.onload = function(e){
                    td2.innerHTML='<font color="red">'+xhr1.responseText+'</font>';
                    if (xhr1.status==200) {
                    var html=JSON.parse(xhr1.responseText);
                    if (!html['uploadUrl']) {
                        td2.innerHTML='<font color="red">'+xhr1.responseText+'</font><br>';
                        uploadbuttonshow();
                    } else {
                        td2.innerHTML='开始上传 ...';
                        binupfile(file,html['uploadUrl'],timea+'_'+i);
                    }
                    }
                    if (i<files.length-1) {
                        i++;
                        getuplink(i);
                    }
                }
                }
            }
            function size_format(num)
            {
                if (num>1024) {
                    num=(num/1024);
                } else {
                    return num.toFixed(2) + ' B';
                }
                if (num>1024) {
                    num=Number((num/1024).toFixed(2));
                } else {
                    return num.toFixed(2) + ' KB';
                }
                if (num>1024) {
                    num=Number((num/1024).toFixed(2));
                } else {
                    return num.toFixed(2) + ' MB';
                }
                return num.toFixed(2) + ' GB';
            }
            function binupfile(file,url,tdnum){
                var label=document.getElementById('upfile_td2_'+tdnum);
                var reader = new FileReader();
                var StartStr='';
                var MiddleStr='';
                var StartTime;
                var EndTime;
                var newstartsize = 0;
                if(!!file){
                    var asize=0;
                    var totalsize=file.size;
                    var xhr2 = new XMLHttpRequest();
                    xhr2.open("GET", url);
                    //xhr2.setRequestHeader('x-requested-with','XMLHttpRequest');
                    xhr2.send(null);
                    xhr2.onload = function(e){
                        var html=JSON.parse(xhr2.responseText);
                        var a=html['nextExpectedRanges'][0];
                        asize=Number( a.slice(0,a.indexOf("-")) );
                        StartTime = new Date();
                        newstartsize = asize;
                        if (asize==0) {
                            StartStr='开始于：' +StartTime.toLocaleString()+'<br>' ;
                        } else {
                            StartStr='上次上传'+size_format(asize)+ '<br>本次开始于：' +StartTime.toLocaleString()+'<br>' ;
                        }
                        //label.innerHTML=StartStr+ '已经上传：' +size_format(asize)+ '/'+size_format(totalsize) + '：' + (asize*100/totalsize).toFixed(2) + '%';
                    var chunksize=5*1024*1024; // 每小块上传大小，最大60M，微软建议10M
                    if (totalsize>200*1024*1024) chunksize=10*1024*1024;
                    function readblob(start) {
                        var end=start+chunksize;
                        var blob = file.slice(start,end);
                        reader.readAsArrayBuffer(blob);
                    }
                    readblob(asize);
                    reader.onload = function(e){
                        var binary = this.result;
                        var xhr = new XMLHttpRequest();
                        xhr.open("PUT", url, true);
    //xhr.setRequestHeader('x-requested-with','XMLHttpRequest');
                        bsize=asize+e.loaded-1;
                        xhr.setRequestHeader('Content-Range', 'bytes ' + asize + '-' + bsize +'/'+ totalsize);
                        xhr.upload.onprogress = function(e){
                            if (e.lengthComputable) {
                                var tmptime = new Date();
                                var tmpspeed = e.loaded*1000/(tmptime.getTime()-C_starttime.getTime());
                                var remaintime = (totalsize-asize-e.loaded)/tmpspeed;
                                label.innerHTML=StartStr+'已经上传 ' +size_format(asize+e.loaded)+ ' / '+size_format(totalsize) + ' = ' + ((asize+e.loaded)*100/totalsize).toFixed(2) + '% 平均速度：'+size_format((asize+e.loaded-newstartsize)*1000/(tmptime.getTime()-StartTime.getTime()))+'/s<br>即时速度 '+size_format(tmpspeed)+'/s 预计还要 '+remaintime.toFixed(1)+'s';
                            }
                        }
                        var C_starttime = new Date();
                        xhr.onload = function(e){
                            if (xhr.status<500) {
                            var response=JSON.parse(xhr.responseText);
                            if (response['size']>0) {
                                // 有size说明是最终返回，上传结束
                                var xhr3 = new XMLHttpRequest();
                                xhr3.open("POST", '');
                                xhr3.setRequestHeader('x-requested-with','XMLHttpRequest');
                                xhr3.send('action=del_upload_cache&filename=.'+file.lastModified+ '_' +file.size+ '_' +encodeURIComponent(file.name)+'.tmp');
                                xhr3.onload = function(e){
                                    console.log(xhr3.responseText+','+xhr3.status);
                                }
                                EndTime=new Date();
                                MiddleStr = '结束于：'+EndTime.toLocaleString()+'<br>';
                                if (newstartsize==0) {
                                    MiddleStr += '平均速度：'+size_format(totalsize*1000/(EndTime.getTime()-StartTime.getTime()))+'/s<br>';
                                } else {
                                    MiddleStr += '本次平均速度：'+size_format((totalsize-newstartsize)*1000/(EndTime.getTime()-StartTime.getTime()))+'/s<br>';
                                }
                                document.getElementById('upfile_td1_'+tdnum).innerHTML+='<br><font color="green">上传完成</font>';
                                label.innerHTML=StartStr+MiddleStr;
                                uploadbuttonshow();
                                addelement(response);
                            } else {
                                if (!response['nextExpectedRanges']) {
                                    label.innerHTML='<font color="red">'+xhr.responseText+'</font><br>';
                                } else {
                                    //var C_endtime = new Date();
                                    var a=response['nextExpectedRanges'][0];
                                    asize=Number( a.slice(0,a.indexOf("-")) );
                                    //MiddleStr = '小块速度：'+size_format(chunksize*1000/(C_endtime.getTime()-C_starttime.getTime()))+'/s 平均速度：'+size_format((asize-newstartsize)*1000/(C_endtime.getTime()-StartTime.getTime()))+'/s<br>';
                                    readblob(asize);
                                }
                            } } else readblob(asize);
                        }
                        xhr.send(binary);
                    } }
                }
            }
<?php } else if (getenv('admin')!='') { ?>
        function login()
        {

            // document.body.style = '';
            document.getElementById('mask').style.display='initial';
            const max=(a,b)=>a>b?a:b;
            document.getElementById('mask').style.height=max(window.innerHeight,document.scrollingElement.scrollHeight)+'px';
            document.getElementById('login_div').style.display='initial';
            document.getElementById('login_input').focus();
        }
<?php } ?>
    </script>
    <script src="//s0.pstatp.com/cdn/expire-1-M/ionicons/4.5.6/ionicons.js"></script>
    <!-- <script src="//unpkg.zhimg.com/ionicons@4.4.4/dist/ionicons.js"></script> -->
    <script opacity='0.4' zIndex="-2" count="66" src="//s0.pstatp.com/cdn/expire-1-M/canvas-nest.js/2.0.4/canvas-nest.js"></script>
    <script>
        document.body.style = '';
    </script>
    </html>
    <?php
    $html=ob_get_clean();
    return output($html,$statusCode);
}
