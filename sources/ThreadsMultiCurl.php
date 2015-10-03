<?php
class TMC{
	static $PreMC=array();	// свежесозданные хэндлы
	static $InStack=array();// в очереди на добавление
	static $InMC=array();	// в исполнении
	static $PostMC=array();	// по завершению хэндл попадает сюда
	static $mch=false;
	static $Pars=array();	// параметры для последних функций на каждый поток
	static $Funcs=array();	// последние функции на каждый поток
	static $ARRAYs=array();
	static $id=0;	// последний id в $PreMC
	static $maxConnections=20;	// одновременно выполняющиеся запросы
	static $OPT_TIMEOUT=10;
	
	// инициализация
	static function Init(){
		self::$mch=curl_multi_init();
	}
	
	// обработка расширения
	static function Process(){
		if(sizeof(self::$InMC)==0) return false;
		//if(curl_multi_select(self::$mch)>0){ // если есть готовые дескрипторы
		$mrc = curl_multi_exec(self::$mch, $active);
		global $APPLICATION;
		while(false!==($x=curl_multi_info_read(self::$mch))){	// читаем сообщения
			$id=array_search($x['handle'],self::$InMC);
			// pre(curl_getinfo ($x['handle'], CURLINFO_HTTP_CODE));
			// pre($x['result']);
			// pre(curl_error($x['handle']));
			self::CH_finished($id,$x['result']);
			$APPLICATION->processMessages();
		}
		// self::CH_finished
	}
	
	// создаём поток
	// $ARRAY - массив 0=>URL [, 1=>AJAX=false] [, 2=>post?]
	// $EndFunc - функция, вызываемая по завершению запроса.
	// В неё передадутся параметры $Pars, но первый параметр - $id ресурса в self::$PostMC
	static function NewThread($ARRAY, $EndFunc=null, $Pars=array(),$addheads=array()){
		$ch1=curl_init();
		if(!is_array($ARRAY)) $ARRAY=array($ARRAY);
		$URL=$ARRAY[0];
		$JS_AJAX = isset($ARRAY[1])? $ARRAY[1] : false;
		$heads=array(
				'Connection: keep-alive',
				'Keep-Alive: 60',
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: ru-ru,ru;q=0.8,en-us;q=0.5,en;q=0.3',
				'Cache-Control: max-age=0'
			);
		$heads=array_merge($heads,$addheads);
		if($JS_AJAX) $heads[]='X-Requested-With: XMLHttpRequest';
		curl_setopt($ch1, CURLOPT_URL, $URL);
		curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:14.0) Gecko/20100101 Firefox/14.0.1'); 
		curl_setopt($ch1, CURLOPT_ENCODING, 'gzip, deflate'); 
		curl_setopt($ch1, CURLOPT_HTTPHEADER, $heads); 
 		curl_setopt($ch1, CURLOPT_HEADER, false);
		curl_setopt($ch1, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, false);
		//curl_setopt($ch1, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch1, CURLOPT_TIMEOUT, self::$OPT_TIMEOUT);
		$id=self::add_CH_to_PreMC($ch1);
		self::$ARRAYs[$id]=&$ARRAY;
		self::$Funcs[$id]=&$EndFunc;
		self::$Pars[$id]=&$Pars;
		return $id;
	}
	// назначаем опцию
	static function CurlSetOpt($id, $key, $value){
		if(!isset(self::$PreMC[$id])) return false;
		return curl_setopt(self::$PreMC[$id], $key, $value);
	}
	// читаем опцию после выполнения запроса
	static function CurlGetOpt($id, $key=null){
		if(!isset(self::$PostMC[$id])) return false;
		return curl_getinfo(self::$PreMC[$id], $key);
	}
	// запускаем
	static function Go($id,$now=false){
		if(!isset(self::$PreMC[$id])) return false;
		if(self::$mch===false) self::Init();
		self::$InStack[$id]=self::$PreMC[$id];
		unset(self::$PreMC[$id]);
		if($now) self::InStack_to_InMC($id);
		else self::ProcStack();
	}
	// уничтожаем поток
	static function Destroy($id){
		if(!isset(self::$PreMC[$id]))
		if(!isset(self::$InStack[$id]))
		if(!isset(self::$PostMC[$id]))
		if(!isset(self::$InMC[$id])) return false;
		else {
			curl_multi_remove_handle(self::$mch, self::$InMC[$id]);
			curl_close(self::$InMC[$id]);
			unset(self::$InMC[$id]);
		}
		else $name='PostMC';
		else $name='InStack';
		else $name='PreMC';
		curl_close(self::${$name}[$id]);
		unset(self::${$name}[$id]);
		unset(self::$Funcs[$id], self::$PostMC[$id], self::$ARRAYs[$id]);
		return true;
	}
	// уничтожаем поток
	static function &GetContent($id){
		if(!isset(self::$PostMC[$id])) return false;
		$x=curl_multi_getcontent(self::$PostMC[$id]);
		return $x;
	}
	// Узнать сервер, на который обращался этот запрос
	static function GetServer($id){
		$cat=self::FindCat($id);
		if(false===$cat) return false;
		return parse_url($ARRAYs[0], PHP_URL_HOST);
	}
	
	//===================================//
	// а эти функции мы трогать не будем!//
	//===================================//
	// вставляем хэндл в список и получаем ID хэндла в массиве self::$PreMC
	function add_CH_to_PreMC($ch){
		//self::$PostMC
		$id=++self::$id;
		self::$PreMC[$id]=$ch;
		return $id;
	}
	function InStack_to_InMC($id){
		self::$InMC[$id]=&self::$InStack[$id];
		unset(self::$InStack[$id]);
		curl_multi_add_handle(self::$mch, self::$InMC[$id]);
	}
	// запрос выполнен
	function CH_finished($id,$result=null){
		self::$PostMC[$id]=&self::$InMC[$id];
		unset(self::$InMC[$id]);
		//if($result===CURLE_OK) 
		//$content=curl_multi_getcontent(self::$PostMC[$id]);
		curl_multi_remove_handle(self::$mch, self::$PostMC[$id]);
		//alert('Callable?');
		if(is_callable(self::$Funcs[$id])){
			//alert('Callable!');
			$pars=array($id);
			//$pars[1]&=$content;
			if(!is_array(self::$Pars[$id])) self::$Pars[$id]=array(self::$Pars[$id]);
			call_user_func_array(self::$Funcs[$id], array_merge($pars,self::$Pars[$id]));
		}
		self::Destroy($id);
		self::ProcStack();
	}
	// обработка очереди
	function ProcStack(){
		if(sizeof(self::$InStack)==0) return false;
		if(sizeof(self::$InMC)>=self::$maxConnections) return false;
		$j=min(self::$maxConnections-sizeof(self::$InMC), sizeof(self::$InStack));
		$ks=array_keys(self::$InStack);
		for($i=0;$i<$j;++$i)
			self::InStack_to_InMC($ks[$i]);
		return true;
	}
	// найти, в каком массиве стоит этот ID
	function FindCat($id){
		if(!isset(self::$PreMC[$id]))
		if(!isset(self::$InStack[$id]))
		if(!isset(self::$PostMC[$id]))
		if(!isset(self::$InMC[$id])) return false;
		else $name='InMC';
		else $name='PostMC';
		else $name='InStack';
		else $name='PreMC';
		return $name;
	}
	
}
?>