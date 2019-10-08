<?php
load()->func('communication');
load()->model('cloud');
load()->func('file');
load()->func('az');

	global $_W,$_GPC;  
    if ($_GPC['op'] == 'yun') {
	$op = $_GPC['op'];
	$ver = $_GPC['dataurl'];
	$ver = base64_decode($ver); 
	$ver = $ver - 0.1;
	$hosturl = $_SERVER['HTTP_HOST'];
	$updatehost = 'http://wq.efwww.com/update.php';
    $lastver = file_get_contents($updatehost . '?a=check&v='.$ver);
	$updatehosturl = $updatehost . '?a=update&v=' . $ver . '&u=' . $hosturl;
	$updatenowinfo = file_get_contents($updatehosturl);

        //$vern = $ver+0.1;
      	$prturl = $updatehost . '?a=pra&v=' . $ver. '&u=' . $hosturl;
		$pr = file_get_contents($prturl);
      	$prcl = explode('<br>',$pr);
        $packet['files'] = $prcl;
        $packet['type'] = '';  

		if (strstr($updatenowinfo, 'zip')){			
				$pathurl = $updatehost . '?a=down&f=' . $updatenowinfo. '&u=' . $hosturl;
				$updatedir = IA_ROOT.'/data/update';
				if(!is_dir($updatedir)) {
					mkdirs($updatedir);
				}	

				$isgot = get_file($pathurl, $updatenowinfo, $updatedir);
				
				if($isgot){				
					$updatezip = $updatedir . '/' . $updatenowinfo;
					require_once IA_ROOT.'/framework/library/phpexcel/PHPExcel/Shared/PCLZip/pclzip.lib.php';
					$thisfolder = new PclZip($updatezip);
					$isextract = $thisfolder->extract(PCLZIP_OPT_PATH, $updatedir);
					if ($isextract == 0) {  
						itoast('解压安装包失败！',referer(),'error'); 
					} 
					$archive = new PclZip($updatezip);
					$list = $archive->extract(PCLZIP_OPT_PATH, IA_ROOT, PCLZIP_OPT_REPLACE_NEWER); 
					
					if ($list == 0) {  
						itoast('远程下载模块文件不存在或站点没有读写权限,下载安装包失败！',referer(),'error'); 
					} 
					
					if(file_exists($updatedir . '/update.sql')) {						
						$sqlfile = $updatedir . '/update.sql';

						runquery($sqlfile);               
					}
					                                  
					$newver = "<?php return array ('ver' => '$lastver');?>";
					$f = fopen(IA_ROOT.'/web/key.php','w+');
					fwrite($f,$newver);
					fclose($f);
					@unlink(IA_ROOT . '/update.sql');                  
					deldir($updatedir);
				}
				else{
					itoast('查找不到模块安装包！',referer(),'error');
				}
		}
		else {
		itoast('您未购买易福源码网授权，请联系QQ63779278处理！', url('system/module/not_installed'), 'error');
	}
		
    }
template('cloud/az');
