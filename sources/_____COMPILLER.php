<?php
$A=array(
	'CLASS TRTaskManager.php',
	'Class WinAPI_USER.php',
	'Index.php',
	'ThreadsMultiCurl.php',
	'wb_generic.inc.php',
	'wb_resources.inc.php',
	'wb_windows.inc.php',
);
$inc='';

foreach($A as $v){
	$name=substr(md5($v),8,-8).'.phb';
	echo "* compile   $v   to   $name\n";
	Compile('./'.$v, '../files/scripts/'.$name);
	$inc.="\r\n	require_once(getfilename(DOC_ROOT.'/files/scripts/{$name}'));";
}
function compile($in, $out){
	if(is_file($out)){
		unlink($out);
	}
	$fh = fopen($out, 'w');
	bcompiler_write_header($fh);
	bcompiler_write_file($fh, $in);
	bcompiler_write_footer($fh);
	fclose($fh);
}
echo "* generate   include.php\n";
$C=<<<FUC
<?php
{$inc}
?>
FUC;

file_put_contents('../scripts/include.php',$C);
?>
done
