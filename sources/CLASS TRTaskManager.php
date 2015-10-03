<?
class TRTaskMamager{	// 
	static $Timing=array();	// id=>time
	static $Functions=array();	// id=>array(0=>FUNCTION(name, array),1=>PARAMETRS(array()))
	static $id=0;
	
	function process(){
		$ks=array_keys(self::$Timing);
		$time=microtime(true);
		for($i=0,$j=sizeof($ks);$i<$j;++$i){
			$id=$ks[$i];
			if(self::$Timing[$id]>$time) continue;
			$Func=&self::$Functions[$id];
			$Timing=&self::$Timing[$id];
			unset(self::$Functions[$id],self::$Timing[$id]);
			call_user_func_array($Func[0],$Func[1]);
			$time=microtime(true);
		}
	}
	function addTask($time,$function,$params){
		if($time<=microtime(true)) $time+=microtime(true);
		$id=self::$id++;
		self::$Timing[$id]=$time;
		self::$Functions[$id]=array( $function, $params );
		return $id;
	}
	
}	
?>