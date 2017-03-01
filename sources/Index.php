<?php
$wb_crls = array(
    'IndexWindow',
    'IndexPopUpTray',
    'IndexTrayIcon',
    'FloatWindow',
    'WB_FloatWindow',
    'WB_FloatWindow_Timer',
    'WB_FloatWindow_Title',
    'WB_FloatWindow_Font',
    'WB_FloatWindow_Labels',
);
$s = 1000;
foreach ($wb_crls as $c) {
    ++$s;
    if (!defined($c)) define($c, $s);
}
unset($s, $c, $wb_crls);

class dsSpy {

    static $wb_windows = array();
    static $wb_controls = array();
    static $LastEventTime = 0;
    static $Host = 'community.develstudio.org';
    static $LastPost = array(/*
		array of ThemeID=>array(
			0=>null,	// id пользователя
			1=>null,	// id сообщениея
			2=>null,	// время datetime
		)//*/
    );
    static $LastPostTiming = array();
    static $WinPositions = array();    // позиции окон и размеры их wid=>array(0-x 1-y 2-w 3-h)
    static $Avatars = array();    // uid=>str/true/false  -  str - путь до файла, true - загружается, false - юзер без авы
    static $Options = array(
        'downloadImages'  => true,
        'autocloseWindow' => 600,
        'messageAge'      => 300,
        'skin'            => 0,
        'alphaBlend'      => 255,
        'dblClckTime'     => 0.2,
        'freezeMessage'   => 300,
    );
    static $WaitingAvatars = array();
    static $Skins = array();//init
    static $Pause = false;
    static $MouseEvents = array();
    static $TrayIcon = false;

    function skin($value, $key = 0) {
        return (is_array(self::$Skins[self::$Options['skin']][$value])
            ? (isset(self::$Skins[self::$Options['skin']][$value][$key])
                ? self::$Skins[self::$Options['skin']][$value][$key]
                : self::$Skins[self::$Options['skin']][$value][0])
            : self::$Skins[self::$Options['skin']][$value]);
    }
    function init() {
        self::$TrayIcon = DOC_ROOT . '/icon.ico';
        self::$Skins[0] = array(
            'BGColor'        => clWhite,
            'TitleColor'     => clBlack,
            'TextColor'      => clBlack,
            'DateTimeColor'  => clBlack,
            'AuthorColor'    => array(0xAA0000, 0),
            'CategoryColor'  => array(0x00A100, 0x008A00),
            'ThemeColor'     => array(0x00AA00, 0x007A00),
            'ToMessageColor' => array(0x888888, 0x444444),
            'WinImageLT'     => DOC_ROOT . '/files/images/Move.png',
            'WinImageRT'     => DOC_ROOT . '/files/images/Hide.png',
            'WinImageLD'     => DOC_ROOT . '/files/images/SW.png',
            'WinImageRD'     => DOC_ROOT . '/files/images/Stick.png',
            'NotAvatar'      => DOC_ROOT . '/files/images/NoAvatar.png',
        );
        self::$Skins[1] = array(
            'BGColor'        => 0x222222,
            'TitleColor'     => clWhite,
            'TextColor'      => clWhite,
            'DateTimeColor'  => clGray,
            'AuthorColor'    => array(0x1111CC, 0x1122FF),
            'CategoryColor'  => array(0x00A1A1, 0x00CFCF),
            'ThemeColor'     => array(0x00AAAA, 0x00DDDD),
            'ToMessageColor' => array(0x888888, 0xBBBBBB),
            'WinImageLT'     => DOC_ROOT . '/files/images/DarkMove.png',
            'WinImageRT'     => DOC_ROOT . '/files/images/DarkHide.png',
            'WinImageLD'     => DOC_ROOT . '/files/images/DarkSW.png',
            'WinImageRD'     => DOC_ROOT . '/files/images/DarkStick.png',
            'NotAvatar'      => DOC_ROOT . '/files/images/NoAvatar.png',
        );
        err_no();
        $x = file_get_contents(DOC_ROOT . '/files/lstpst.json');
        $x = $x ? self::LoadValue($x) : false;
        self::$LastPost = $x ? $x : array();
        $x = file_get_contents(DOC_ROOT . '/files/wnpstns.json');
        $x = $x ? self::LoadValue($x) : false;
        self::$WinPositions = $x ? $x : array();
        $x = file_get_contents(DOC_ROOT . '/files/vtrs.json');
        $x = $x ? self::LoadValue($x) : false;
        self::$Avatars = $x ? $x : array();
        $x = file_get_contents(DOC_ROOT . '/files/ptns.json');
        $x = $x ? self::LoadValue($x) : false;
        self::$Options = $x
            ? $x
            : array(
                'downloadImages'  => true,
                'autocloseWindow' => 600,
                'messageAge'      => 300,
                'skin'            => 0,
                'alphaBlend'      => 255,
            );
        $dr = realpath(DOC_ROOT);
        for ($ks = array_keys(self::$Avatars), $j = sizeof($ks), $i = 0; $i < $j; ++$i) {
            $k = $ks[$i];
            if (!is_string(self::$Avatars[$k])) unset(self::$Avatars[$k]);
            if (!is_file(self::$Avatars[$k])) unset(self::$Avatars[$k]);

            $p = realpath(self::$Avatars[$k]);
            if (0 === strpos($p, $dr)) {
                self::$Avatars[$k] = '.' . substr($p, strlen($dr));
            }
        }
        $avaDir = DOC_ROOT . '/files/temp';
        if (!is_dir($avaDir)) mkdir($avaDir);
        # поиск аватарок в папке аватарок
        $files = scandir($avaDir);
        $avaDir .= '/';
        for ($i = 0, $j = sizeof($files); $i < $j; ++$i) {
            $fl = $files[$i];
            if (!is_file($avaDir . $fl)) continue;
            if (preg_match('/Ava_(\d+)\.png/i', $fl, $m)) {
                $id = (int)$m[1];
                if (isset(self::$Avatars[$id])) continue;
                self::$Avatars[$id] = '.\\files\\temp\\' . $fl;
            }
        }
        $dblClckTime = (int)WinAPI_USER::GetDoubleClickTime() / 1000;
        if (!isset(self::$Options['freezeMessage']) or self::$Options['freezeMessage'] <= 0)
            self::$Options['freezeMessage'] = 300;
        if (!isset(self::$Options['dblClckTime']) or self::$Options['dblClckTime'] <= 0)
            self::$Options['dblClckTime'] = $dblClckTime > 0.1 ? $dblClckTime : 0.5;


        self::$LastEventTime = mktime();
        self::$WaitingAvatars = array();
        self::startScan(30);
        self::$wb_windows[IndexWindow] = c('Index');
        self::$wb_controls[IndexWindow][IndexTrayIcon] = c('Index->trayIcon');
        self::$wb_controls[IndexWindow][IndexTrayIcon]->iconFile = realpath(self::$TrayIcon);
    }
    function goToLastEvent() {

    }
    function startScan($cnt = 15) {
        if (self::$Pause) return false;
        $url = 'http://' . self::$Host . '/misc.php?show=latestposts&vsacb_resnr=' . $cnt;
        $EndFunc = function ($ch) { dsSpy::analize(TMC::GetContent($ch)); };
        $tmc = TMC::NewThread(array($url, true), $EndFunc);
        TMC::Go($tmc);
    }
    function searchNews(&$content) {    // поиск чего-то нового
        if (sizeof($content) == 0) return array();
        $old = self::$LastPost;
        self::$LastPost = array();
        $cont = array_values(
            $content
        );    //theme, themeuri, themeid, author, authorid, authoruri, messageuri, messageid, datetime, cat, caturi
        $r = array();
        $notAddNewItems = false;
        $news = -1;
        for ($i = 0, $j = sizeof($cont); $i < $j; ++$i) {
            $authorId = $cont[$i]['authorid'];
            $themeId = $cont[$i]['themeid'];
            $messageId = $cont[$i]['messageid'];
            $datetime = $cont[$i]['datetime'];
            self::$LastPost[$themeId] = array($authorId, $messageId, $datetime);

            if (!isset($old[$themeId])) {
                if (!$notAddNewItems) {
                    $r[] = $cont[$i];
                    self::$LastPostTiming[$themeId] = mktime();
                }
                continue;
            }
            if (!isset(self::$LastPostTiming[$themeId])) 0;//self::$LastPostTiming[$themeId]=intval(mktime()-self::$Options['freezeMessage']/2);
            if ($authorId === $old[$themeId][0] && $messageId === $old[$themeId][1] && $datetime === $old[$themeId][2]) {
                if (!$notAddNewItems) $news = sizeof($r);
                $notAddNewItems = true;
                //	if(self::$WaitingAvatars['autocloseWindow'] > mktime()-$datetime) $r[]=$cont[$i];
                if (abs(mktime() - self::$LastPostTiming[$themeId]) > self::$Options['freezeMessage'])
                    continue;
            } else self::$LastPostTiming[$themeId] = mktime();
            $r[] = $cont[$i];
        }
        if ($news == 0) return array();
        //pre($r);
        return $r;
    }
    function analize(&$cont) {
        $a = self::parse($cont);
        if (!is_array($a)) return false;//alert('not array');
        $a = self::searchNews($a);
        $cnt = sizeof($a);
        if ($cnt == 0) return false;
        self::$LastEventTime = mktime();
        $wid = FloatWindow;
        $Wa = explode(' ', wb_get_system_info('workarea'));
        $x = isset(dsSpy::$WinPositions[$wid][0]) ? dsSpy::$WinPositions[$wid][0] : null;
        $w = 340;
        $h1 = isset(dsSpy::$WinPositions[$wid][3]) ? dsSpy::$WinPositions[$wid][3] : 400;

        $y2 = isset(dsSpy::$WinPositions[$wid][1]) ? dsSpy::$WinPositions[$wid][1] + $h1 : null;
        $h2 = ($h2 = isset($y2) ? $y2 - $Wa[1] : $Wa[1]) > 0 ? $h2 : $Wa[3];

        $h = max(min($h2, $cnt * 110 + 40), 30);
        $y = isset(dsSpy::$WinPositions[$wid][1]) ? dsSpy::$WinPositions[$wid][1] + $h1 - $h : null;

        self::gotWindow(
            $wid,
            self::$WaitingAvatars['autocloseWindow'],
            $Title = 'Новые сообщения [' . $cnt . ' шт.]',
            $Type = 1,
            $a,
            $x,
            $y,
            $w,
            $h
        );
    }
    function parse(&$txt) {
        $r = array();
        $lines = explode('</tr>', $txt);
        for ($i = 0, $j = sizeof($lines); $i < $j; ++$i) {
            $cols = explode('</td>', $lines[$i]);
            if (sizeof($cols) < 6) continue;
            $r[$i] = array();
            for ($a = 0, $b = sizeof($cols); $a < $b; ++$a) {
                $str = trim(strip_tags($cols[$a], '<a>'));
                switch ($a) {
                    case 1 :
                        $r[$i]['theme'] = trim(html_entity_decode(strip_tags($str)));
                        $r[$i]['themeuri'] = self::_get_string_chunk($str, 'href="', '"', null, true);
                        $r[$i]['themeid'] = intval(self::_get_string_chunk($r[$i]['themeuri'], 't=', null, null, true));
                        $r[$i]['themefull'] = htmlspecialchars_decode(
                            self::_get_string_chunk($cols[$a], 'title="', '"', null, true)
                        );
                        break;
                    case 2 :
                        $str2 = explode('</a>', $str);
                        $r[$i]['author'] = trim(html_entity_decode(strip_tags($str2[0])));
                        $r[$i]['authoruri'] = htmlspecialchars_decode(
                            self::_get_string_chunk($str2[0], 'href="', '"', null, true)
                        );
                        $r[$i]['authorid'] = intval(
                            self::_get_string_chunk($r[$i]['authoruri'], 'u=', null, null, true)
                        );
                        $r[$i]['messageuri'] = htmlspecialchars_decode(
                            self::_get_string_chunk($str2[1], 'href="', '"', null, true)
                        );

                        $r[$i]['messageid'] = intval(
                            self::_get_string_chunk(strtolower($r[$i]['messageuri']), '#post', null, null, true)
                        );
                        break;
                    case 3 :
                        $t = trim(strip_tags($str));
                        sscanf($t, '%d-%d, %d:%d', $d, $m, $H, $ii);
                        $r[$i]['datetime'] = mktime($H, $ii, 0, $m, $d);
                        break;
                    case 4 :
                        $r[$i]['cat'] = trim(html_entity_decode(strip_tags($str)));
                        $r[$i]['caturi'] = htmlspecialchars_decode(
                            self::_get_string_chunk($str, 'href="', '"', null, true)
                        );
                        break;
                }
            }
        }
        return $r;
    }
    function _get_string_chunk(&$str, $s1, $s2 = null, $offset = 0, $splitS1 = false) {    // вырезать из строки кусок от $s1 до $s2
        if (false === ($x1 = mb_strpos($str, $s1, $offset))) return false;
        $ss1 = mb_strlen($s1);
        if (is_null($s2)) $x2 = mb_strlen($str);
        elseif (false === ($x2 = mb_strpos($str, $s2, $x1 + $ss1))) return false;
        if ($splitS1) $x1 += $ss1;
        return mb_substr($str, $x1, $x2 - $x1);
    }
    function TMCTimer() {
        TRTaskMamager::process();
        TMC::Process();
    }


    function wb_procWindow($Win, $ID, $Ctrl = null, $ParA = null, $ParB = null) {
        $wid = WB_FloatWindow;    // window id
        if ($Win == self::$wb_windows[$wid])
            if ($ParA == (WBC_LBUTTON | WBC_MOUSEUP)) {
                //	if(wb_get_size($ОКНА[ОКНО1])===WBC_MINIMIZED)wb_set_size($ОКНА[ОКНО1],WBC_NORMAL);
                self::goToLastEvent();
                self::wb_hideWindow($wid);
            }
        if ($ParA == (WBC_RBUTTON | WBC_MOUSEUP)) self::wb_hideWindow($wid);
        if (isset(self::$wb_windows[$wid]) && $ID === IDCLOSE) self::wb_hideWindow($wid);
        if (isset(self::$wb_windows[$wid]) && $ID === WB_FloatWindow_Timer) self::wb_hideWindow($wid);
    }
    function wb_hideWindow($wid, $timer = null) {
        if (isset(self::$wb_windows[$wid])) {
            if (!is_null($timer)) wb_destroy_timer(self::$wb_windows[$wid], $timer);
            wb_set_visible(self::$wb_windows[$wid], false);
        }
    }
    function wb_gotWindow($Timer = 0, $Title = 'Привет! :)', $Text = array(), $Wdth = 220, $Rate = 0.75 /*разделитель на 2 столбика*/, $X = null, $Y = null) {//© roxblnfk ;)
        $wid = WB_FloatWindow;    // window id
        $Wa = explode(' ', wb_get_system_info('workarea'));
        $W = intval(max($Wdth, 200));
        $H = 150;
        WinAPI_USER::GetCursorPos($xx, $yy);
        if (is_array($Text)) {
            if (($j = count($Text)) > 5) $H = 50 + $j * 20;
        } else $H = 200;
        if (!isset($X)) $X = $Wa[2] - $W; elseif ($X === true) $X = $xx;
        if (!isset($Y)) $Y = $Wa[3] - $H; elseif ($Y === true) $Y = $yy;
        if (isset(self::$wb_windows[$wid])) {
            $NEW = false;
            /* if(wb_get_visible(self::$wb_windows[$wid])) wb_destroy_timer(self::$wb_windows[$wid],WB_FloatWindow_Timer);
            else wb_set_visible(self::$wb_windows[$wid],true);
            wb_set_position(self::$wb_windows[$wid],$X,$Y);
            wb_set_size(self::$wb_windows[$wid], $W, $H);
            wb_set_size(self::$wb_controls[$wid][WB_FloatWindow_Title],$W-4,20);
            wb_set_text(self::$wb_controls[$wid][WB_FloatWindow_Title],$Title);
            wb_set_position(self::$wb_controls[$wid][WB_FloatWindow_Title],2,2); */
            $keys = array_keys(self::$wb_controls[$wid][WB_FloatWindow_Labels]);
            for ($i = 0, $j = count($keys); $i < $j; $i++) {
                wb_destroy_control(self::$wb_controls[$wid][WB_FloatWindow_Labels][$keys[$i]]);
                unset(self::$wb_controls[$wid][WB_FloatWindow_Labels][$keys[$i]]);
                // if(isset(self::$wb_controls[$wid][СКЕТЧ ЦЕННОСТЬ][$keys[$i]])){
                // wb_destroy_control(self::$wb_controls[$wid][СКЕТЧ ЦЕННОСТЬ][$keys[$i]]);
                // unset(self::$wb_controls[$wid][СКЕТЧ ЦЕННОСТЬ][$keys[$i]]);
                // }
            }
        } else {
            $NEW = true;
            self::$wb_windows[$wid] = wb_create_window(
                null,
                NakedWindow,
                "ololo",
                $X,
                $Y,
                $W,
                $H, /* WBC_TASKBAR |  */
                WBC_NOTIFY, /* WBC_DBLCLICK | WBC_HEADERSEL |  */
                WBC_MOUSEUP
            );
            //wb_set_visible(self::$wb_windows[$wid], false);
            //alert(self::$wb_windows[$wid]);
            self::$wb_controls[$wid] = array();
            self::$wb_controls[$wid][WB_FloatWindow_Title] = wb_create_control(
                self::$wb_windows[$wid],
                Label,
                $Title,
                2,
                2,
                $W - 4,
                20,
                WB_FloatWindow_Title,
                WBC_CENTER,
                0,
                0
            );
        }
        /* if(is_array($Text)){
            $keys=array_keys($Text);
            $l1=intval(($W-15)*$Rate);
            for($i=0,$j=count($keys);$i<$j;$i++){
                $k=$keys[$i];
                if(!is_array($Text[$k]))$Text[$k]=array($Text[$k]);
                if(count($Text[$k])>=2){
                    self::$wb_controls[$wid][WB_FloatWindow_Labels][$i]=	wb_create_control(self::$wb_windows[$wid],Label,$Text[$k][0],10,30+$i*20,$l1,20,0,WBC_RIGHT,0,0);
                    // self::$wb_controls[$wid][СКЕТЧ ЦЕННОСТЬ][$i]=	wb_create_control(self::$wb_windows[$wid],Label,$Text[$k][1],15+$l1,30+$i*20,$W-20-$l1,20,0,WBC_LEFT,0,0);
                    continue;
                }else{
                    $Text[$k]=reset($Text[$k]);
                    self::$wb_controls[$wid][WB_FloatWindow_Labels][$i]=	wb_create_control(self::$wb_windows[$wid],Label,strval($Text[$k]),10,30+$i*20,$W-15,20,0,WBC_CENTER,0,0);
                }
            }
        }else self::$wb_controls[$wid][WB_FloatWindow_Labels][0]=	wb_create_control(self::$wb_windows[$wid],RTFEditBox,strval($Text),2,25,$W-4,$H-40,WB_FloatWindow_Labels,WBC_READONLY,0,0);
        //*/
        if ($Timer < 100) $Timer = intval(2147483640);
        wb_create_timer(self::$wb_windows[$wid], WB_FloatWindow_Timer, $Timer);
        if ($NEW) {
            self::$wb_controls[$wid][WB_FloatWindow_Font] = wb_create_font('Calibri', 12, BLACK, FTA_BOLD);
            wb_set_font(self::$wb_controls[$wid][WB_FloatWindow_Title], self::$wb_controls[$wid][WB_FloatWindow_Font]);
            wb_set_handler(self::$wb_windows[$wid], 'dsSpy::wb_procWindow');
        }
        wb_set_size(self::$wb_windows[$wid], WBC_NORMAL);
        //self::showWindow(self::$wb_windows[$wid],500);
        //WinAPI_USER::CloseWindow(wb_get_id(self::$wb_windows[$wid]));
    }
    function gotWindow($wid = null, $Timer = 0, $Title = 'Привет! :)', $Type = 0, $TextBlocks = array(), $X = null, $Y = null, $Wdth = 360, $Hgnt = 500) {//© roxblnfk ;)
        if (!isset($wid)) $wid = FloatWindow;    // window id
        $Wa = explode(' ', wb_get_system_info('workarea'));
        $W = intval(min(max(40, $Wdth), $Wa[2]));
        $H = intval(min(max(40, $Hgnt), $Wa[3]));
        WinAPI_USER::GetCursorPos($xx, $yy);
        if (!isset($X)) $X = $Wa[2] - $W; elseif ($X === true) $X = $xx;
        if (!isset($Y)) $Y = $Wa[3] - $H; elseif ($Y === true) $Y = $yy;
        if (!isset(self::$wb_windows[$wid])) {
            $NEW = true;
            self::$wb_windows[$wid] = new TForm();
            self::$wb_windows[$wid]->clientWidth = $W;
            self::$wb_windows[$wid]->borderStyle = bsNone;//bsSizeToolWin;//bsToolWindow;//bsSingle;//bsNone;
            self::$wb_windows[$wid]->color = 0x00123456;
            self::$wb_windows[$wid]->caption = 'ололололо';
            self::$wb_windows[$wid]->doubleBuffered = true;
            self::$wb_windows[$wid]->transparentColor = true;
            self::$wb_windows[$wid]->transparentColorValue = 0x00123456;
            self::$wb_windows[$wid]->alphaBlend = true;
            self::$wb_windows[$wid]->alphaBlendValue = dsSpy::$Options['alphaBlend'];
            self::$wb_windows[$wid]->formStyle = fsStayOnTop;
            //self::$wb_windows[$wid]->onClosequery='dsSpy::CloseProgram';
            //self::$wb_windows[$wid]->onClose='dsSpy::CloseProgram';
            WinAPI_USER::SetParent(self::$wb_windows[$wid]->handler);

            //	self::$wb_controls[$wid][WB_FloatWindow_Title]=wb_create_control(self::$wb_windows[$wid],Label,$Title,2,2,$W-4,20,WB_FloatWindow_Title,WBC_CENTER,0,0);
        } else {
            $NEW = false;
            // уничтодить имеющиеся компоненты на форме
            for ($i = 1, $j = sizeof(self::$wb_controls[$wid]); $i < $j; ++$i) {
                self::$wb_controls[$wid][$i]->free();
            }
            if (isset(self::$wb_controls[$wid][0])) self::$wb_controls[$wid][0]->free();
            unset(self::$wb_controls[$wid]);
        }
        self::$wb_windows[$wid]->x = $X;
        self::$wb_windows[$wid]->y = $Y;
        self::$wb_windows[$wid]->w = $W;
        self::$wb_windows[$wid]->h = $H;
        self::$wb_controls[$wid] = array();

        // автоскрытие окна
        $function = function ($LastEventTime, $wid) {
            if ($LastEventTime == dsSpy::$LastEventTime) WinAPI_USER::AnimateWindow(
                dsSpy::$wb_windows[$wid]->handle,
                null,
                0x00010004
            );
        };
        if ($Timer > 1) TRTaskMamager::addTask($Timer, $function, array(self::$LastEventTime, $wid));    // автоскрытие
        if ($NEW) {
            // события
            $onMouseDown = function ($id, $button, $shift, $x, $y) use (&$wid) {
                $self = c($id);
                if ($button === 0)    // нажата только левая клавиша
                    dsSpy::goToLastEvent();
                //if( $button === 1)	// нажата только правая клавиша
                //if( $button === 2)	// нажата только средняя
                WinAPI_USER::AnimateWindow(dsSpy::$wb_windows[$wid]->handle, null, 0x00010004);    // скрыть
            };
            //self::$wb_windows[$wid]->onMouseDown=$onMouseDown;
            self::$wb_windows[$wid]->hide();
        }
        // заполняем форму компонентами
        $b = 5;    // border
        $i = 0;
        $x = 0;
        $y = 0;
        $w = $W;
        $h = 20;
        switch ($Type) {
            case 0 :
                break; // пустое окно
            case 1 :        // новое сообщение		 theme, themeuri, themeid, author, authorid, authoruri, messageuri, messageid, datetime, cat, caturi
                // title
                self::$wb_controls[$wid][++$i] = self::ds_createImage(self::$wb_windows[$wid], $x, $y, 20, $h);
                self::$wb_controls[$wid][$i]->loadFromFile(self::skin('WinImageLT'));
                self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Передвинуть окно');
                $OnResize = array(false, $X, $Y, $W, $H);    // изменение размера и перемещение окна
                $onMouseDown = function ($id, $button, $shift, $x, $y) use (&$wid, &$OnResize) {
                    WinAPI_USER::GetCursorPos($xx, $yy);
                    $OnResize = array(true, $xx, $yy);
                };
                $onMouseUp = function ($id, $button, $shift, $x, $y) use (&$wid, &$OnResize) {
                    $OnResize[0] = false;
                    dsSpy::$WinPositions[$wid] = array(
                        dsSpy::$wb_windows[$wid]->x,
                        dsSpy::$wb_windows[$wid]->y,
                        dsSpy::$wb_windows[$wid]->w,
                        dsSpy::$wb_windows[$wid]->h,
                    );
                };
                self::$wb_controls[$wid][$i]->cursor = crSize;
                self::$wb_controls[$wid][$i]->onMouseMove = function ($id, $shift, $x, $y) use (&$wid, &$OnResize) {    // перемещение
                    if (true !== $OnResize[0]) return false;
                    WinAPI_USER::GetCursorPos($xx1, $yy1);
                    $xx0 = $OnResize[1];
                    $yy0 = $OnResize[2];
                    $OnResize[1] = $xx1;
                    $OnResize[2] = $yy1;
                    $nx = dsSpy::$wb_windows[$wid]->x + $xx1 - $xx0;
                    $ny = dsSpy::$wb_windows[$wid]->y + $yy1 - $yy0;
                    $Wa = explode(' ', wb_get_system_info('workarea'));
                    if ($nx < $Wa[0] || $ny < $Wa[1] || $nx >= $Wa[2] || $ny >= $Wa[3]) return false;
                    dsSpy::$wb_windows[$wid]->x = dsSpy::$wb_windows[$wid]->x + $xx1 - $xx0;
                    dsSpy::$wb_windows[$wid]->y = dsSpy::$wb_windows[$wid]->y + $yy1 - $yy0;
                };
                self::$wb_controls[$wid][$i]->onMouseDown = $onMouseDown;
                self::$wb_controls[$wid][$i]->onMouseUp = $onMouseUp;

                self::$wb_controls[$wid][++$i] = self::ds_createImage(self::$wb_windows[$wid], $w - 20, $y, 20, $h);
                self::$wb_controls[$wid][$i]->loadFromFile(self::skin('WinImageRT'));
                self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Скрыть окно');
                self::$wb_controls[$wid][$i]->cursor = crHandPoint;
                self::$wb_controls[$wid][$i]->onClick = function ($id) use (&$wid) {
                    WinAPI_USER::AnimateWindow(
                        dsSpy::$wb_windows[$wid]->handle,
                        null,
                        0x00010004
                    );
                };

                $DownItems = array();    // элементы внизу окна

                self::$wb_controls[$wid][++$i] = self::ds_createImage(self::$wb_windows[$wid], $x, $H - $h, 20, $h);
                self::$wb_controls[$wid][$i]->loadFromFile(self::skin('WinImageLD'));
                self::$wb_controls[$wid][$i]->cursor = crSizeNESW;
                self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Прозрачность');
                $OnAlpha = array(false, $X, $Y);    // изменение размера и перемещение окна
                self::$wb_controls[$wid][$i]->onMouseDown = function ($id, $button, $shift, $x, $y) use (&$wid, &$OnAlpha) {
                    WinAPI_USER::GetCursorPos($xx, $yy);
                    $OnAlpha = array(true, $xx, $yy);
                };
                self::$wb_controls[$wid][$i]->onMouseUp = function ($id, $button, $shift, $x, $y) use (&$wid, &$OnAlpha) { $OnAlpha[0] = false; };
                self::$wb_controls[$wid][$i]->onMouseMove = function ($id, $shift, $x, $y) use (&$wid, &$OnAlpha) {    // перемещение
                    if (true !== $OnAlpha[0]) return false;
                    WinAPI_USER::GetCursorPos($xx1, $yy1);
                    $xx0 = $OnAlpha[1];
                    $yy0 = $OnAlpha[2];
                    $OnAlpha[1] = $xx1;
                    $OnAlpha[2] = $yy1;
                    $na = min(255, max(20, dsSpy::$wb_windows[$wid]->alphaBlendValue + $xx1 - $xx0 + $yy0 - $yy1));
                    dsSpy::$Options['alphaBlend'] = $na;
                    dsSpy::$wb_windows[$wid]->alphaBlendValue = $na;
                };
                $DownItems[] = $i;

                self::$wb_controls[$wid][++$i] = self::ds_createLabel(
                    self::$wb_windows[$wid],
                    $x + 20,
                    $H - $h,
                    $w - 40,
                    $h,
                    'dsSpy © roxblnfk 2012-2017',
                    taCenter,
                    fsBold,
                    10,
                    self::skin('TextColor')
                );
                self::$wb_controls[$wid][$i]->transparent = false;
                self::$wb_controls[$wid][$i]->cursor = crHelp;
                self::$wb_controls[$wid][$i]->layout = tlCenter;
                self::$wb_controls[$wid][$i]->color = self::skin('BGColor');
                //self::$wb_controls[$wid][$i]->hint=self::encoding_toGUI('Перейти на сайт '.dsSpy::$Host);
                self::$wb_controls[$wid][$i]->onDblClick = function ($id) {
                    ++dsSpy::$Options['skin'];
                    if (!isset(dsSpy::$Skins[dsSpy::$Options['skin']])) dsSpy::$Options['skin'] = 0;
                };
                $DownItems[] = $i;

                self::$wb_controls[$wid][++$i] = self::ds_createImage(
                    self::$wb_windows[$wid],
                    $w - 20,
                    $H - $h,
                    20,
                    $h
                );
                self::$wb_controls[$wid][$i]->loadFromFile(self::skin('WinImageRD'));
                self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Прилепить в парвый шижний угол');
                self::$wb_controls[$wid][$i]->cursor = crHandPoint;
                self::$wb_controls[$wid][$i]->onClick = function ($id) use (&$wid, &$OnResize) {
                    $Wa = explode(' ', wb_get_system_info('workarea'));
                    dsSpy::$WinPositions[$wid][0] = dsSpy::$WinPositions[$wid][1] = null;
                    dsSpy::$wb_windows[$wid]->x = $Wa[2] - dsSpy::$wb_windows[$wid]->w;
                    dsSpy::$wb_windows[$wid]->y = $Wa[3] - dsSpy::$wb_windows[$wid]->h;
                };
                $DownItems[] = $i;

                self::$wb_controls[$wid][++$i] = self::ds_createLabel(
                    self::$wb_windows[$wid],
                    $x + 20,
                    $y,
                    $w - 40,
                    $h,
                    $Title,
                    taCenter,
                    fsBold,
                    10,
                    self::skin('TitleColor')
                );
                self::$wb_controls[$wid][$i]->transparent = false;
                self::$wb_controls[$wid][$i]->layout = tlCenter;
                self::$wb_controls[$wid][$i]->color = self::skin('BGColor');
                self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Растянуть окно');
                self::$wb_controls[$wid][$i]->onMouseDown = $onMouseDown;
                self::$wb_controls[$wid][$i]->onMouseUp = $onMouseUp;
                self::$wb_controls[$wid][$i]->cursor = crSizeNS;
                self::$wb_controls[$wid][$i]->onMouseMove = function ($id, $shift, $x, $y) use (&$wid, &$OnResize, &$DownItems) {    // расширение
                    if (true !== $OnResize[0]) return false;
                    WinAPI_USER::GetCursorPos($xx1, $yy1);
                    $Wa = explode(' ', wb_get_system_info('workarea'));
                    $xx0 = $OnResize[1];
                    $yy0 = $OnResize[2];
                    $OnResize[1] = $xx1;
                    $OnResize[2] = $yy1;
                    //dsSpy::$wb_windows[$wid]->clientWidth =dsSpy::$wb_windows[$wid]->clientWidth +$xx0-$xx1;
                    $nh = dsSpy::$wb_windows[$wid]->h + $yy0 - $yy1;
                    $ny = dsSpy::$wb_windows[$wid]->y + $yy1 - $yy0;
                    if ($nh < 40 || $ny < $Wa[1] || $ny >= $Wa[3]) return false;
                    dsSpy::$wb_controls[$wid][0]->h = dsSpy::$wb_controls[$wid][0]->h + $nh - dsSpy::$wb_windows[$wid]->h;
                    for ($i = 0, $j = sizeof(
                        $DownItems
                    ); $i < $j; ++$i) dsSpy::$wb_controls[$wid][$DownItems[$i]]->y = dsSpy::$wb_controls[$wid][$DownItems[$i]]->y + $nh - dsSpy::$wb_windows[$wid]->h;
                    dsSpy::$wb_windows[$wid]->h = $nh;
                    //dsSpy::$wb_windows[$wid]->x=dsSpy::$wb_windows[$wid]->x+$xx1-$xx0;
                    dsSpy::$wb_windows[$wid]->y = $ny;
                };
                unset($OnResize, $DownItems);
                // scroll Box
                self::$wb_controls[$wid][0] = self::ds_createScrollBox(
                    self::$wb_windows[$wid],
                    0,
                    $y + $h,
                    $W,
                    $H - $x - $h - $h,
                    bsNone
                );
                self::$wb_controls[$wid][0]->doubleBuffered = true;
                self::$wb_controls[$wid][0]->color = self::skin('BGColor');
                $parent =& self::$wb_controls[$wid][0];
                $ww = $W - 16;
                $y = -10;

                # функции скролла окна
                $fn_scrollState = array();
                $fn_scrollMouseDown = function ($id, $button, $shift, $x, $y) use (&$parent, &$fn_scrollState) {
                    WinAPI_USER::GetCursorPos($xx, $yy);
                    $fn_scrollState = array(true, $xx, $yy);
                };
                $fn_scrollMouseUp = function ($id, $button, $shift, $x, $y) use (&$parent, &$fn_scrollState) { $fn_scrollState[0] = false; };
                $fn_scrollMouseMove = function ($id, $shift, $x, $y) use (&$parent, &$fn_scrollState) {    // перемещение
                    if (!$fn_scrollState || true !== $fn_scrollState[0]) return;
                    WinAPI_USER::GetCursorPos($xx1, $yy1);
                    // $xx0 = $fn_scrollState[1];
                    $yy0 = $fn_scrollState[2];
                    $dy = $yy0 - $yy1;
                    $up = $dy < 0;
                    $sens = 4;
                    if (abs($dy) > $sens) {
                        $cnt = floor(abs($dy) / $sens);
                        for ($i = 0; $i < $cnt; ++$i)
                            $parent->perform(277, (int)!$up, 0);
                        $fn_scrollState[2] = $yy1 - $dy % $sens;
                    }
                };
                $parent->onMouseDown = $fn_scrollMouseDown;
                $parent->onMouseUp = $fn_scrollMouseUp;
                $parent->onMouseMove = $fn_scrollMouseMove;

                for ($i0 = 0, $j0 = sizeof($TextBlocks); $i0 < $j0; ++$i0) {
                    $Text =& $TextBlocks[$i0];
                    /////////////
                    $w = $ww;
                    $x = 0;
                    // picture
                    self::$wb_controls[$wid][++$i] = self::ds_createImage($parent, $x += $b, $y += $h, $w = 100, 100);
                    self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Аватарка пользователя');
                    // кач картинки
                    if (!isset(self::$Avatars[$Text['authorid']]) && self::$Options['downloadImages']) {
                        self::$Avatars[$Text['authorid']] = true;
                        if (!isset(self::$WaitingAvatars[$Text['authorid']])) self::$WaitingAvatars[$Text['authorid']] = array($i);

                        $ip = gethostbyname(self::$Host);
                        $url = 'http://' . $ip . '/image.php?u=' . $Text['authorid'] . '&dateline=1341764643';
                        $addheads = array('Host: ' . self::$Host);
                        $EndFunc = function ($ch, $wid, $LastEventTime, $AuthorID) {
                            $img = TMC::GetContent($ch);
                            err_no();
                            $im = @imagecreatefromstring($img);
                            if (false === $im) {
                                //alert($img);
                                unset(dsSpy::$Avatars[$AuthorID]);
                                return false;
                            }
                            if (imagesx($im) < 10 || imagesy($im) < 10) {
                                dsSpy::$Avatars[$AuthorID] = false;
                                return false;
                            }
                            //wb_play_sound(WBC_INFO);
                            $file = DOC_ROOT . '/files/temp/Ava_' . $AuthorID . '.png';
                            imagepng($im, $file);
                            dsSpy::$Avatars[$AuthorID] = $file;
                            //alert(strlen($img));
                            ///file_put_contents('f:/suck.jpg',$img);
                            //dsSpy::$wb_controls[$wid][$id]->picture->clear();
                            if (dsSpy::$LastEventTime !== $LastEventTime) return false;
                            //dsSpy::$wb_controls[$wid][$id]->picture->loadFromStr(dsSpy::ImageToStr($im),'png');
                            if (is_file($file)) {
                                if (!isset(dsSpy::$WaitingAvatars[$AuthorID])) return true;
                                for ($i = 0, $j = sizeof(dsSpy::$WaitingAvatars[$AuthorID]); $i < $j; ++$i) {
                                    $id = dsSpy::$WaitingAvatars[$AuthorID][$i];
                                    //alert($id);
                                    dsSpy::$wb_controls[$wid][$id]->picture->loadFromFile($file, 'png');
                                }
                            } else unset(dsSpy::$Avatars[$AuthorID]);
                        };
                        $tmc = TMC::NewThread(
                            $url,
                            $EndFunc,
                            array($wid, self::$LastEventTime, $Text['authorid']),
                            $addheads
                        );
                        TMC::Go($tmc);
                    }
                    if (is_string(self::$Avatars[$Text['authorid']]))
                        if (is_file(
                            self::$Avatars[$Text['authorid']]
                        )) dsSpy::$wb_controls[$wid][$i]->picture->loadFromFile(
                            self::$Avatars[$Text['authorid']],
                            'png'
                        );
                        else unset(self::$Avatars[$Text['authorid']]);
                    elseif (self::$Avatars[$Text['authorid']] === true)
                        self::$WaitingAvatars[$Text['authorid']][] = $i;
                    self::$wb_controls[$wid][$i]->onMouseDown = $fn_scrollMouseDown;
                    self::$wb_controls[$wid][$i]->onMouseUp = $fn_scrollMouseUp;
                    self::$wb_controls[$wid][$i]->onMouseMove = $fn_scrollMouseMove;
                    ////////////////
                    $x += $w + $b;
                    $w = $ww - $x - $b - $b;
                    // date time
                    self::$wb_controls[$wid][++$i] = self::ds_createLabel(
                        $parent,
                        $x,
                        $y,
                        $w,
                        $h = 20,
                        date('H:i, d M', $Text['datetime']),
                        taRightJustify,
                        null,
                        null,
                        self::skin('DateTimeColor')
                    );
                    self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Время публикации сообщения');
                    self::$wb_controls[$wid][$i]->autosize = true;
                    self::$wb_controls[$wid][$i]->onMouseEnter = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'DateTimeColor',
                            1
                        );
                    };
                    self::$wb_controls[$wid][$i]->onMouseLeave = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'DateTimeColor',
                            0
                        );
                    };
                    // name
                    self::$wb_controls[$wid][++$i] = self::ds_createLabel(
                        $parent,
                        $x,
                        $y,
                        $w,
                        $h = 20,
                        $Text['author'],
                        null,
                        fsBold,
                        11,
                        self::skin('AuthorColor')
                    );
                    self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Пользователь');
                    self::$wb_controls[$wid][$i]->autosize = true;
                    self::$wb_controls[$wid][$i]->cursor = crHandPoint;
                    self::$wb_controls[$wid][$i]->onMouseEnter = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'AuthorColor',
                            1
                        );
                    };
                    self::$wb_controls[$wid][$i]->onMouseLeave = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'AuthorColor',
                            0
                        );
                    };
                    $url = 'http://' . self::$Host . '/' . $Text['authoruri'];
                    self::$wb_controls[$wid][$i]->onClick = function ($id) use (&$url) { dsSpy::RunURL($url); };
                    unset($url);
                    // cat
                    self::$wb_controls[$wid][++$i] = self::ds_createLabel(
                        $parent,
                        $x,
                        $y += $h + 5,
                        $w,
                        $h = 15,
                        '<< ' . $Text['cat'] . ' >>',
                        taCenter,
                        fsBold,
                        8,
                        self::skin('CategoryColor')
                    );
                    self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI('Раздел');
                    self::$wb_controls[$wid][$i]->cursor = crHandPoint;
                    self::$wb_controls[$wid][$i]->onMouseEnter = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'CategoryColor',
                            1
                        );
                    };
                    self::$wb_controls[$wid][$i]->onMouseLeave = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'CategoryColor',
                            0
                        );
                    };
                    $url = 'http://' . self::$Host . '/' . $Text['caturi'];
                    self::$wb_controls[$wid][$i]->onClick = function ($id) use (&$url) { dsSpy::RunURL($url); };
                    unset($url);
                    // theme
                    $themtxt = self::encoding_toGUI($Text['themefull']);
                    self::$wb_controls[$wid][++$i] = self::ds_createLabel(
                        $parent,
                        $x,
                        $y += $h,
                        $w,
                        $h = 40,
                        mb_substr($Text['themefull'], 0, 80) . (mb_strlen($Text['themefull']) > 80 ? '…' : '')
                        ,
                        taCenter,
                        fsBold,
                        9,
                        self::skin('ThemeColor')
                    );
                    self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI($Text['themefull']);
                    self::$wb_controls[$wid][$i]->wordWrap = true;
                    self::$wb_controls[$wid][$i]->cursor = crHandPoint;
                    self::$wb_controls[$wid][$i]->layout = tlCenter;
                    self::$wb_controls[$wid][$i]->onMouseEnter = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'ThemeColor',
                            1
                        );
                    };
                    self::$wb_controls[$wid][$i]->onMouseLeave = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'ThemeColor',
                            0
                        );
                    };
                    $url = 'http://' . self::$Host . '/' . $Text['themeuri'];
                    self::$wb_controls[$wid][$i]->onClick = function ($id) use (&$url) { dsSpy::RunURL($url); };
                    unset($url);
                    // post
                    self::$wb_controls[$wid][++$i] = self::ds_createLabel(
                        $parent,
                        $x,
                        $y += $h,
                        $w,
                        $h = 20,
                        '#' . $Text['messageid'] . ' перейти',
                        taCenter,
                        fsUnderline,
                        9,
                        self::skin('ToMessageColor')
                    );
                    self::$wb_controls[$wid][$i]->hint = self::encoding_toGUI(
                        'Перейти к первому непрочитанному сообщению в теме'
                    );
                    self::$wb_controls[$wid][$i]->cursor = crHandPoint;
                    self::$wb_controls[$wid][$i]->onMouseEnter = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'ToMessageColor',
                            1
                        );
                    };
                    self::$wb_controls[$wid][$i]->onMouseLeave = function ($id) {
                        c($id)->font->color = dsSpy::skin(
                            'ToMessageColor',
                            0
                        );
                    };
                    $url = 'http://' . self::$Host . '/' . $Text['messageuri'];
                    self::$wb_controls[$wid][$i]->onClick = function ($id) use (&$url) { dsSpy::RunURL($url); };
                    unset($url);
                    $h += 10;    // промежуток
                }
                wb_play_sound(WBC_INFO);
                break;
        }


        //wb_set_size(self::$wb_windows[$wid],WBC_NORMAL);
        //self::showWindow(self::$wb_windows[$wid],500);
        self::showWindow($wid);
        return $wid;
    }
    function showWindow($wid) {
        if (!isset(self::$wb_windows[$wid])) return false;
        WinAPI_USER::AnimateWindow(self::$wb_windows[$wid]->handle, null, 0x00000008);
        self::$wb_windows[$wid]->show();    // чтобы не пропадал скроллбокс :D
        WinAPI_USER::BringWindowToTop(self::$wb_windows[$wid]->handle);
        //self::$wb_windows[$wid]->toFront();
        self::$wb_windows[$wid]->repaint();
        self::$wb_controls[$wid][0]->repaint();
    }
    function ImageToStr(&$img) {
        ob_start();
        imagepng($img);
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
    }
    function ds_createImage(&$form, $x = 0, $y = 0, $w = 16, $h = 16, $avatar = true) {
        $obj = new TMImage($form);
        $obj->parent = $form;
        $obj->transparent = false;
        $obj->proportional = true;
        $obj->center = true;
        //$obj->enabled=false;
        $obj->autoSize = false;
        $obj->x = $x;
        $obj->y = $y;
        $obj->w = $w;
        $obj->h = $h;
        if ($avatar === true) $obj->loadFromFile(self::skin('NotAvatar'));
        return $obj;    //loadFromUrl
    }
    function ds_createLabel(&$form, $x = 0, $y = 0, $w = 16, $h = 16, $text, $align = taLeftJustify, $style = fsNormal, $size = 10, $color = clBlack) {
        $obj = new TLabel($form);
        $obj->parent = $form;
        $obj->caption = self::encoding_toGUI($text);
        $obj->autoSize = false;
        $obj->alignment = $align;
        $obj->font->style = $style;
        $obj->font->color = $color;
        $obj->font->size = $size;
        $obj->x = $x;
        $obj->y = $y;
        $obj->w = $w;
        $obj->h = $h;
        return $obj;
    }
    function ds_createScrollBox(&$form, $x = 0, $y = 0, $w = 16, $h = 16, $border = bsSingle) {
        $obj = new TScrollBox($form);
        $obj->parent = $form;
        $obj->autoSize = false;
        $obj->visible = true;
        $obj->borderStyle = $border;
        $obj->bevelWidth = 0;
        //$obj->cursor=crHandPoint;
        $obj->x = $x;
        $obj->y = $y;
        $obj->w = $w;
        $obj->h = $h;
        return $obj;
    }
    function RunURL($url) {
        if (substr($url, 0, 7) == 'http://' || substr($url, 0, 8) == 'https://' || substr(
                $url,
                0,
                6
            ) == 'ftp://'
        ) shell_exec('start ' . escapeshellcmd(str_replace(' ', '%20', $url)));
        else return false;
        return true;
    }
    function encoding_toGUI($str) { return mb_convert_encoding($str, 'cp1251', 'utf8'); }

    function TrayIcon_onMousemove(&$self, $shift, $x, $y) {
        if (isset(self::$wb_windows[FloatWindow])) self::$wb_windows[FloatWindow]->toFront();
        //self::$wb_windows[IndexWindow]->toBack();
    }
    function TrayIcon_onClick(&$self) {
        /* self::$wb_controls[IndexWindow][IndexTrayIcon] */
        $self->iconFile = realpath(self::$TrayIcon);
        //if(isset(self::$wb_windows[FloatWindow])) self::$wb_windows[FloatWindow]->toBack();
        self::ds_createTrayIconPopUpMenu();
    }
    function MouseEvents($time) {    // вызывается из планировщика
        if (self::$MouseEvents[0] > $time) return false;
        switch (self::$MouseEvents[1]) {
            case 1 :
                if (isset(self::$wb_windows[FloatWindow]))
                    self::showWindow(FloatWindow);
                break;
            case 2 :
                self::switchPause();
                break;
        }
        if (self::$MouseEvents[1] > 20) alert(
            self::encoding_toGUI(
                "Жжошь, сцуко!\r\nЕсли щёлкнуть дважды на надписи \"dsSpy © roxblnfk 2012-2017\", то изменится скин программы ;)"
            )
        );
    }
    function TrayIcon_onMouseDown(&$self, $button, $shift, $x, $y) {
        self::$wb_controls[IndexWindow][IndexTrayIcon]->iconFile = realpath(self::$TrayIcon);
    }
    function TrayIcon_onMouseUp(&$self, $button, $shift, $x, $y) {
        if ($button == 1) return self::ds_createTrayIconPopUpMenu();
        if ($button == 2) return self::RunURL('http://' . dsSpy::$Host);
        $mt = microtime(true);
        $preMT = self::$MouseEvents[0];
        self::$MouseEvents[0] = $mt;
        if ($mt - $preMT <= self::$Options['dblClckTime']) ++self::$MouseEvents[1];
        else self::$MouseEvents[1] = 1;
        TRTaskMamager::addTask(self::$Options['dblClckTime'], 'dsSpy::MouseEvents', array($mt));
    }
    function getAutoRun() {
        readRegKey(HKEY_CURRENT_USER, 'Software\\Microsoft\\Windows\\CurrentVersion\\Run\\dsSpy', $x, STRING);
        if (!$x) return false;
        if ($x{0} === '"' && strlen($x) > 2) $x = substr($x, 1, intval(strpos($x, '"', 1)) - 1);
        $x = realpath($x);
        if (!$x) return false;
        return ($x === realpath(EXE_NAME));
    }
    function switchPause() {
        self::$Pause = !self::$Pause;
        self::$TrayIcon = self::$Pause ? DOC_ROOT . '/files/images/iconpause.ico' : DOC_ROOT . '/icon.ico';
        self::$wb_controls[IndexWindow][IndexTrayIcon]->iconFile = realpath(self::$TrayIcon);
    }
    function setAutoRun($ja) {
        // HKCU\Software\Microsoft\Windows\CurrentVersion\Run
        $p = '"' . realpath(EXE_NAME) . '"';
        writeRegKey(
            HKEY_CURRENT_USER,
            'Software\\Microsoft\\Windows\\CurrentVersion\\Run\\dsSpy',
            $ja ? $p : null,
            STRING
        );
        //
    }
    function ds_createTrayIconPopUpMenu() {
        if (!isset(self::$wb_controls[IndexWindow])) self::$wb_controls[IndexWindow] = array();
        self::$wb_controls[IndexWindow][IndexPopUpTray] = false;
        self::$wb_controls[IndexWindow][IndexPopUpTray] = new TPopupMenu;
        $popup =& self::$wb_controls[IndexWindow][IndexPopUpTray];

        if (isset(self::$wb_windows[FloatWindow])) {
            $menu = new TMenuItem;
            $menu->caption = self::encoding_toGUI('Показать окно [1xЛКМ]');
            $menu->onClick = function () { dsSpy::showWindow(FloatWindow); };
            $popup->addItem($menu);
        }

        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI((self::$Pause ? 'Снять с паузы' : 'Пауза') . ' [2xЛКМ]');
        $menu->onClick = function () { dsSpy::switchPause(); };
        $menu->picture->transparent = false;
        $menu->picture->loadFromFile(
            DOC_ROOT . '/files/images/' . (self::$Pause ? 'iconpause0.ico' : 'iconpause1.ico'),
            'ico'
        );
        $popup->addItem($menu);

        // разделитель
        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI('-');
        $popup->addItem($menu);

        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI('Открыть ' . self::$Host . ' [СКМ]');
        $menu->onClick = function () { dsSpy::RunURL('http://' . dsSpy::$Host); };
        $menu->picture->transparent = false;
        $menu->picture->loadFromFile(DOC_ROOT . '/files/images/community_fav.ico', 'ico');
        $popup->addItem($menu);

        // $menu=new TMenuItem;
        // $menu->caption=self::encoding_toGUI('Открыть develnet.ru');
        // $menu->onClick=function(){ dsSpy::RunURL('http://develnet.ru'); };
        // $menu->picture->transparent=false;
        // $menu->picture->loadFromFile(DOC_ROOT.'/files/images/develnet_fav.ico','ico');
        // $popup->addItem($menu);

        // $menu=new TMenuItem;
        // $menu->caption=self::encoding_toGUI('Открыть DS Wiki (ds-wiki.ru)');
        // $menu->onClick=function(){ dsSpy::RunURL('http://ds-wiki.ru'); };
        // $popup->addItem($menu);

        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI('Перейти на страницу DS в VK');
        $menu->onClick = function () { dsSpy::RunURL('http://vk.com/develstudioru'); };
        $menu->picture->transparent = false;
        $menu->picture->loadFromFile(DOC_ROOT . '/files/images/vk_fav.ico', 'ico');
        $popup->addItem($menu);

        // разделитель
        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI('-');
        $popup->addItem($menu);

        $menu = new TMenuItem;
        $act = !self::getAutoRun();
        $menu->caption = self::encoding_toGUI($act ? 'Поставить в автозагрузку' : 'Убрать из автозагрузки');
        $menu->onClick = function () use (&$act) { dsSpy::setAutoRun($act); };
        $popup->addItem($menu);
        unset($act);

        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI(
            self::$Options['downloadImages'] ? 'Запретить загрузку картинок' : 'Разрешить загрузку картинок'
        );
        $menu->onClick = function () { dsSpy::$Options['downloadImages'] = !dsSpy::$Options['downloadImages']; };
        $popup->addItem($menu);

        // разделитель
        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI('-');
        $popup->addItem($menu);

        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI('Закрыть dsSpy');
        $menu->onClick = function () { app::close(); };
        $popup->addItem($menu);

        $menu = new TMenuItem;
        $menu->caption = self::encoding_toGUI('Отмена');
        $popup->addItem($menu);

        WinAPI_USER::GetCursorPos($mx, $my);
        $popup->popup(max(0, $mx - 150), $my + 2);
    }
    function CloseProgram() {
        file_put_contents(DOC_ROOT . '/files/lstpst.json', self::SaveValue(self::$LastPost));
        file_put_contents(DOC_ROOT . '/files/wnpstns.json', self::SaveValue(self::$WinPositions));
        file_put_contents(DOC_ROOT . '/files/vtrs.json', self::SaveValue(self::$Avatars));
        file_put_contents(DOC_ROOT . '/files/ptns.json', self::SaveValue(self::$Options));
    }
    function SaveValue(&$value) {
        // $r = gzdeflate(igbinary_serialize($value));
        $r = json_encode($value/*, JSON_PRETTY_PRINT*/);
        return $r;
    }
    function LoadValue(&$str) {
        // $r = igbinary_unserialize(gzinflate($str));
        $r = json_decode($str, true);
        return $r;
    }
}
