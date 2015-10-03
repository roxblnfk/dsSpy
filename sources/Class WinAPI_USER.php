<?

/*
function ffffuuuuuuuuu(){
	$form=c('Form2');
	$HWND = $form->handle;
	
	$i=50;
	WinAPI_USER::GetCursorPos($x,$y);	// получаем координаты курсора (записываются в $x и $y)
	$W=WinAPI_USER::GetSystemMetrics(0);	// разрешение экрана первичного монитора по горизонтали
	$H=WinAPI_USER::GetSystemMetrics(1);	// разрешение экрана первичного монитора по вертикали
	$SM_CXMIN=WinAPI_USER::GetSystemMetrics(28);	// Минимальная ширина окна в пикселях
	$SM_CYMIN=WinAPI_USER::GetSystemMetrics(29);	// Минимальная высота окна в пикселях
	alert($W.' x '.$H);
	do{
		$h1=WinAPI_USER::WindowFromPoint($x,$y);	// получаем хэндл объекта по координатам $x и $y
		WinAPI_USER::ShowWindow($h1);		// скрываем окно
		WinAPI_USER::MoveWindow(		// двигаем его, меняя размер
						$h1,			// хэндл объекта
						rand(0,300),	// новая позиция X
						rand(0,300),	// новая позиция Y
						rand($SM_CXMIN,2*$SM_CXMIN),	// новая ширина
						max($SM_CYMIN,rand(100,300))	// новая высота
					);
		WinAPI_USER::AnimateWindow($h1);	// и сразу анимированно отображаем сдивинутый объект
		$x=rand(0,$W); $y=rand(0,$H);	// мне повезёт!
	} while(--$i);
	
	//	WinAPI_USER::ShowWindow($HWND,3); // maximize
	//WinAPI_USER::ShowWindow($HWND); // hide
	//WinAPI_USER::AnimateWindow($HWND);
	//WinAPI_USER::CloseWindow($HWND);
	//WinAPI_USER::BringWindowToTop($HWND);
	
	//alert(WinAPI_USER::GetForegroundWindow());
	
	//WinAPI_USER::MoveWindow($HWND,2,2,200,200,true);
	//WinAPI_USER::SetForegroundWindow($HWND);
	////////////////WinAPI_USER::AnyPopup();
	//$a=WinAPI_USER::WindowFromPoint(1,1);
	//$a=WinAPI_USER::SetForegroundWindow($HWND);
	//WinAPI_USER::BeginDeferWindowPos(1);
	
	//\\\alert(serialize(WinAPI_USER::DeferWindowPos(....)));

	
	//WinAPI_USER::MoveWindow($b,100,100,300,300,true);
	//WinAPI_USER::BringWindowToTop($a);
	//$b=WinAPI_USER::ChildWindowFromPointEx($a,300,300);	// fuu
	//WinAPI_USER::MoveWindow($b,2,2,200,200,true);

	
	//WinAPI_USER::GetTopWindow();
	//alert(implode("\r\n",array_keys(WinAPI_USER::$Fns)));
} ///*/
// остановился на EndDeferWindowPos из списка xsnakes
// http://community.develstudio.ru/showthread.php/5704-DLL-WinAPI-by-xsnakes?p=47436&viewfull=1#post47436
class WinAPI_USER {	//© roxblnfk 2012 ;)
	static $Lib=false;
	const LibName='USER';
	static $ffi=false;
	static $Fns=array(	// список доступных функций
				'SetCursorPos'=>true,
				'GetCursorPos'=>true,
				'AnimateWindow'=>true,
				'ShowWindow'=>true,
				'CloseWindow'=>true,	// минимизирует (не уничтожает) окно
				'BringWindowToTop'=>true,	// окно на передний план
				'DestroyWindow'=>true,
				'GetShellWindow'=>true,
				'GetDesktopWindow'=>true,
				'GetForegroundWindow'=>false,
				'MoveWindow'=>true,	// передвинуть окно
				'WindowFromPoint'=>true,	//	взять окно с точки экрана (может быть даже компонентом :)
				'ChildWindowFromPoint'=>true,	//
				'ChildWindowFromPointEx'=>true,	// 
				'GetTopWindow'=>true,	//
				'GetParent'=>true,	//
				'SetParent'=>true,	//
				'GetWindow'=>true,	//
				'SetForegroundWindow'=>true,	//
				'ArrangeIconicWindows'=>true,	//
				'BeginDeferWindowPos'=>true,	//
				'DeferWindowPos'=>true,	// не тестил, ибо лень
				'EndDeferWindowPos'=>true,	// не тестил, ибо лень
				'AnyPopup'=>false,	// багнутая херня, работает 1 раз :)
				
				'GetSystemMetrics'=>true,	// Retrieves the specified system metric or system configuration setting	[int]
				
				'GetDC'=>true,			// return handle to a device context [HDC]
				'ReleaseDC'=>true,	// [int]
				'GetDoubleClickTime'=>false,	// ffi	// in milliseconds
			);
	function  1($n){
		if(self::$ffi===false) self:: initFFI();
		if(self::$Lib===false){ self::$Lib=wb_load_library(self::LibName); if(self::$Lib===false) return false; }
		if(!isset(self::$Fns[$n]) || self::$Fns[$n]===true){
			self::$Lib=wb_load_library(self::LibName);
			self::$Fns[$n]=($x=wb_get_function_address($n, self::$Lib))===null ? false : $x;
		}
		if(self::$Fns[$n]===false){
			return method_exists(self::$ffi,$n);
		}
		return self::$Fns[$n];
	}
	function  initFFI(){
		self::$ffi=new ffi (
			"[lib='user32.dll']
				int MessageBoxA( 
					uint32	handle,
					char	*text,
					char	*caption,
					uint32	type
				);
				uint32 GetForegroundWindow( );
				uint32 AnyPopup( );
				uint32 GetDoubleClickTime( );
			" );
	}
	function SetCursorPos($x=0,$y=0){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$r=wb_call_function($Fn,array($x,$y));
		return (bool)$r;
	}
	function GetCursorPos(&$x,&$y){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$xy=pack('ll',0,0);
		$r=wb_call_function($Fn,array($xy));
		$xy=unpack('la/lb',$xy);
		$x=$xy['a'];
		$y=$xy['b'];
		return (bool)$r;
	}
	function AnimateWindow($hwnd,$dwTime=200,$dwFlags=0x00000010){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$dwTime=(int)$dwTime;
		$dwFlags=(int)$dwFlags;
		$r=wb_call_function($Fn,array($hwnd,$dwTime,$dwFlags));
		return (bool)$r;
	}
	function ShowWindow($hwnd,$nCmdShow=0){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$nCmdShow=(int)$nCmdShow;
		$r=wb_call_function($Fn,array($hwnd,$nCmdShow));
		return (bool)$r;
	}
	function CloseWindow($hwnd){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$r=wb_call_function($Fn,array($hwnd));
		return (bool)$r;
	}
	function BringWindowToTop($hwnd){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$r=wb_call_function($Fn,array($hwnd));
		return (bool)$r;
	}
	function DestroyWindow($hwnd){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$r=wb_call_function($Fn,array($hwnd));
		return (bool)$r;
	}
	function GetShellWindow(){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		return wb_call_function($Fn);
	}
	function GetDesktopWindow(){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		return wb_call_function($Fn);
	}
	function GetForegroundWindow(){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		if(true===$Fn) return self::$ffi->{__FUNCTION__}();
		
		return wb_call_function($Fn);
	}
	function MoveWindow($hwnd,$x,$y,$nWidth,$nHeight,$bRepaint=true){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$x=(int)$x;
		$y=(int)$y;
		$nWidth=(int)$nWidth;
		$nHeight=(int)$nHeight;
		$bRepaint=(bool)$bRepaint;
		$r=wb_call_function($Fn,array($hwnd,$x,$y,$nWidth,$nHeight,$bRepaint));
		return (bool)$r;
	}
	function WindowFromPoint($x,$y){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$x=(int)$x;
		$y=(int)$y;
		return wb_call_function($Fn,array($x,$y));
	}
	function ChildWindowFromPoint($hwnd,$x,$y){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$x=(int)$x;
		$y=(int)$y;
		return wb_call_function($Fn,array($hwnd,$x,$y));
	}
	function ChildWindowFromPointEx($hwnd,$x,$y,$uFlags=0){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$x=(int)$x;
		$y=(int)$y;
		$uFlags=(int)$uFlags;
		return wb_call_function($Fn,array($hwnd,$x,$y,$uFlags));
	}
	function GetTopWindow($hWnd=null){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=is_null($hwnd) ? null : (int)$hwnd;
		return wb_call_function($Fn, array($hWnd));
	}
	function GetParent($hWnd){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		return wb_call_function($Fn, array($hWnd));
	}
	function SetParent($hWndChild,$hWndNewParent=null){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hWndChild=(int)$hWndChild;
		$hWndNewParent=is_null($hWndNewParent) ? null : (int)$hWndNewParent;
		return wb_call_function($Fn, array($hWndChild,$hWndNewParent));
	}
	function GetWindow($hWnd,$uCmd=0){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hWnd=(int)$hWnd;
		$uCmd=(int)$uCmd;
		return wb_call_function($Fn, array($hWnd,$uCmd));
	}
	function SetForegroundWindow($hWnd){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		return wb_call_function($Fn, array($hWnd));
	}
	function ArrangeIconicWindows($hWnd){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hwnd=(int)$hwnd;
		$r=wb_call_function($Fn, array($hWnd));
		return (bool)$r;
	}
	function BeginDeferWindowPos($nNumWindows){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$nNumWindows=(int)$nNumWindows;
		return wb_call_function($Fn, array($nNumWindows));
	}
	function DeferWindowPos($hWinPosInfo,$hWnd,$hWndInsertAfter,$x,$y,$cx,$cy,$uFlags=0x0040){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hWinPosInfo=(int)$hWinPosInfo;			//HDWP 
		$hWnd=(int)$hWnd;						//HWND 
		$hWndInsertAfter=(int)$hWndInsertAfter;	//HWND 
		$x=(int)$x;	
		$y=(int)$y;
		$cx=(int)$cx;
		$cy=(int)$cy;
		$uFlags=(int)$uFlags;
		return wb_call_function($Fn, array($hWinPosInfo,$hWnd,$hWndInsertAfter,$x,$y,$cx,$cy,$uFlags));
	}
	function EndDeferWindowPos($hWinPosInfo){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hWinPosInfo=(int)$hWinPosInfo;	//HDWP
		$r=wb_call_function($Fn, array($nNumWindows));
		return (bool)$r;
	}
	function AnyPopup(){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		if(true===$Fn) return self::$ffi->{__FUNCTION__}();
		
		$r=wb_call_function($Fn);
		return (bool)$r;
	}
	
	function GetSystemMetrics($nIndex=0){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$nIndex=(int)$nIndex;
		return wb_call_function($Fn,array($nIndex));
	}
	
	/// Device Contexts
	function GetDC($hWnd=null){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hWnd=is_null($hWnd) ? null : (int)$hWnd;
		return wb_call_function($Fn,array($hWnd));
	}
	function ReleaseDC($hWnd,$hDC){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		
		$hWnd=(int)$hWnd;	// HWND 
		$hDC=(int)$hDC;		// HDC 
		return wb_call_function($Fn,array($hWnd,$hDC));
	}
	
	
	function GetDoubleClickTime(){
		$Fn=self:: 1(__FUNCTION__); if(false===$Fn) return false;
		if(true===$Fn) return self::$ffi->{__FUNCTION__}();
	}
}
?>