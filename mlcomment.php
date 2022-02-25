#!/usr/bin/php
<?php

///RU \file
///RU Многоязычные комментарии
///RU Скрипт разработан для простого извлечения и документировавания кода с многоязычными комментариями
///RU Разрабатывалось и тестировалось в Debian 11 / PHP 7.4.
///RU \author Дмитрий Дмитриев
///RU \date 02.2022
///RU \copyright MIT
///RU \todo 1. Установка языка (найти все комментарии без языка и установить язык для них)
///RU \todo 2. Авто-перевод комментариев через Google
///EN \file
///EN Multi-language comments
///EN The script is designed for easy extraction and documentation of code with multilingual comments.
///EN Developed and tested under Debian 11 / PHP 7.4.
///EN \author Dmitrii Dmitriev
///EN \date 02.2022
///EN \copyright MIT
///EN \todo 1. Set language (find all comment without language and set language for it)
///EN \todo 2. Auto-translate comments by Google

    class MLComment {
        ///RU Ключ командной строки для указания приоритетов языков
        ///EN Command line option for set languages priority
        const OPT_PRIOR = 'p';

        ///RU Ключ командной строки для выбора языка (этот язык встает на первое место в приоритетах)
        ///EN Command line option for select language (this language moved to the first place in priority)
        const OPT_LANG = 'l';

        ///RU По умолчанию название языка удаляется после обработки, этот ключ позволяет его оставить
        ///EN By default language suffix in comment removed, this option could save it
        const OPT_SAVELANG = 's';

        ///RU Как начинаются однострочные комментарии, можно несколько вариантов через пробел
        ///EN Begin of one-line comments, available space separated list
        const OPT_LINE = 'n';

        ///RU Как открываются многострочные комментарии, можно несколько вариантов через пробел
        ///EN Open of multi-line comments, available space separated list
        const OPT_OPEN = 'o';

        ///RU Как закрываются многострочные комментарии, можно несколько вариантов через пробел
        ///EN Close of multi-line comments, available space separated list
        const OPT_CLOSE = 'c';

        ///RU JSON-файл с доступами к Yandex Cloud с полями: folder_id И (oauth_token ИЛИ iam_token)
        ///EN JSON-file with access to Yandex Cloud with fields: folder_id AND (oauth_token OR iam_token)
        const OPT_YANDEX = 'y';

        ///RU Не выводить символ червового комментария DRAFT
        ///EN Don't attach DRAFT sign to translated comments
        const OPT_NODRAFT = 'd';

        ///RU Результат работы скрипта записать назад в файл поверх
        ///EN Overwrite result of patch to source file
        const OPT_INPLACE = 'i';

        ///RU Вывод справки
        ///EN Show help
        const OPT_HELP = 'h';

        ///RU Предобработка файла для Doxygen
        ///EN Preprocessing comments for Doxygen
        const MODE_DOX = 'dox';

        ///RU Оставить комментарии только на самом приоритетном языке
        ///EN Only most priority language comments
        const MODE_MOST = 'most';

        ///RU Добавить комментарии на указанном языке автопереводом с существующего языка по приоритетам
        ///EN Add comments in selected language by auto-translate from most priority exists language
        const MODE_TRANSLATE = 'translate';

        ///RU Все режимы
        ///EN All modes
        protected static $modes = [ self::MODE_DOX, self::MODE_MOST, self::MODE_TRANSLATE ];

        ///RU Приоритет языков по умолчанию
        ///EN Priority of languages by default
        protected static $langs = [ 'EN', 'RU', 'ES', 'FR', 'IT' ];

        ///RU Выбранный самый приоритетный язык
        ///EN Selected most priority language
        protected static $lang = '';

        ///RU Начало однострочного комментария
        ///EN Begin of one-lime comment
        protected static $line = ['//'];

        ///RU Открытие многострочного комментария
        ///EN Open of multi-line comment
        protected static $open = ['/*'];

        ///RU Закрытие многострочного комментария
        ///EN Close of multi-line comment
        protected static $close = ['*/'];

        ///RU Сохранять ли язык в комментариях
        ///EN Save language in comments
        protected static $savelang = false;

        ///RU Выбранный режим
        ///EN Selected mode
        protected static $mode = '';

        ///RU Файл для обработки, '-' - stdin
        ///EN File for patch, '-' - stdin
        protected static $filename = '';

        ///RU Разделительный символ для паттера регулярки
        ///EN Delimiter symbol of regexp pattern
        const D = '/';

        ///RU Символ чернового комментария
        ///EN Symbol of draft comment
        const DRAFT = '~';

        ///RU RegExp для извлечения одного комментария с языками
        ///EN RegExp for one comment with languages
        protected static $reone;

        ///RU RegExp для блока комментариев на разных языках (из которых будет выбирать приоритетный)
        ///EN RegExp for block of multi-language comments (most priority language will be selected)
        protected static $reblock;

        ///RU Доступы к Yandex Cloud
        ///RU Access data for Yandex Cloud
        protected static $yandex;

        ///RU Выводить символ DRAFT для переведенных комментариев
        ///EN Attach sign DRAFT to translated comments
        protected static $draft = true;

        ///RU Результат работы скрипта записать назад в файл поверх, иначе вывести в stdout
        ///EN Overwrite result of patch to source file, else write to stdout
        protected static $inplace = false;

        const YANDEX_FOLDER_ID = 'folder_id';
        const YANDEX_OAUTH_TOKEN = 'oauth_token';
        const YANDEX_IAM_TOKEN = 'iam_token';

        public static function run(array $argv):bool {
            if (!static::parseArgs($argv)) return false;
            static::prepareRE();
            return static::patch();
        }

        ///RU Разбор аргументов командной строки
        ///EN Parse command line arguments
        protected static function parseArgs(array $argv): bool {
            $errors = [];
            for ($i = 1; $i < count($argv); ++$i) {
                $arg = $argv[$i]; $len = mb_strlen($arg);
                if (('-' == mb_substr($arg, 0, 1)) and ($len > 1)) {
                    $opt = mb_substr($arg, 1, 1);//RU Опция
                    if ($len > 2) {
                        $v = trim(mb_substr($arg, 2), '"\'');//RU Значение
                        $in = "in '$arg'";
                    } else {
                        $v = trim($argv[++$i] ?? '', '"\'');//RU Значение
                        $in = "in '-$opt $v'";
                    }
                    switch ($opt) {
                    case static::OPT_PRIOR: {
                        $langs = explode(' ', $v);
                        if (!$langs) $errors[] = "Not found priority list for -$opt $in";
                        else {
                            foreach ($langs as &$lang) {
                                if (!preg_match('/^[a-zA-Z]{2}$/u', $lang)) $errors[] = "Unknown language '$lang' $in, language must be 2-letter code";
                                $lang = mb_strtoupper($lang);
                            }
                            unset($lang);
                            static::$langs = $langs;
                        }
                    };break;
                    case static::OPT_LANG: {
                        if (!preg_match('/^[a-zA-Z]{2}$/u', $v)) $errors[] = "Unknown language '$v' $in, language must be 2-letter code";
                        else static::$lang = mb_strtoupper($v);
                    };break;
                    case static::OPT_SAVELANG: { if (2 == $len) --$i; static::$savelang = true; };break;
                    case static::OPT_LINE: {
                        if (!$v) $errors[] = "Begin of one-line comment -$opt can't be empty $in";
                        else static::$line = static::vlist($v);
                    };break;
                    case static::OPT_OPEN: {
                        if (!$v) $errors[] = "Open of multi-line comment -$opt can't be empty $in";
                        else static::$open = static::vlist($v);
                    };break;
                    case static::OPT_CLOSE: {
                        if (!$v) $errors[] = "Close of multi-line comment -$opt can't be empty $in";
                        else static::$close = static::vlist($v);
                    };break;
                    case static::OPT_YANDEX: {
                        if (!$v) $errors[] = "JSON-file for Yandex Cloud -$opt can't be empty $in";
                        else if (!file_exists($v)) $errors[] = "Not found JSON-file '$v' $in";
                        else if ((!($json = @file_get_contents($v))) || (!($yandex = @json_decode($json, true))) || (!(static::yandexCheck($yandex)))) {
                            $errors[] = "Invalid JSON-file '$v' $in";
                        } else static::$yandex = $yandex;
                    };break;
                    case static::OPT_NODRAFT: { if (2 == $len) --$i; static::$draft = false; };break;
                    case static::OPT_INPLACE: { if (2 == $len) --$i; static::$inplace = true; };break;
                    case static::OPT_HELP: {
                        static::usage($argv);
                        return false;
                    }
                    default: $errors[] = "Unknown option '$opt' $in";
                    }
                } else if (!static::$mode) {
                    if (!in_array($arg, static::$modes, true)) $errors[] = "Unknown mode '$arg'";
                    else static:: $mode = $arg;
                } else if (!static::$filename) {
                    if ('-' !== $arg) {
                        if (!file_exists($arg)) $errors[] = "Not found file '$arg'";
                    }
                    static::$filename = $arg;
                } else $errors[] = "Try use many files in '$arg'";
            }
            if (!static::$lang) static::$lang = reset(static::$langs);
            else if (reset(static::$langs) !== static::$lang) {
                $i = array_search(static::$lang, static::$langs, true);
                if (false === $i) $errors[] = "Not found language ".static::$lang." in piority list: ".implode(' ', static::$langs);
                else {
                    unset(static::$langs[$i]);
                    array_unshift(static::$langs, static::$lang);
                    static::$langs = array_values(static::$langs);
                }
            }
            if (count(static::$open) != count(static::$close)) $errors[] = "Count of begin multi-line and end multi-line comments must be equal";
            if (!static::$mode) $errors[] = "Mode is required";
            if (!static::$filename) $errors[] = "Filename is required";
            if ((static::MODE_TRANSLATE === static::$mode) && (!static::$yandex)) $errors[] = "-".static::OPT_YANDEX." is required";
            if ($errors) {
                static::stderr(implode("\n", $errors));
                static::usage($argv, true);
                return false;
            }
            return true;
        }

        ///RU Вывод справки о параметрах
        ///EN Print usage of script
        public static function usage($argv, bool $tostderr = false):void {
            fwrite($tostderr ? STDERR : STDOUT, implode("\n", [
                "Usage: ".basename($argv[0]).
                    " [-".static::OPT_PRIOR.' "'.implode(' ', static::$langs).'"'."]".
                    " [-".static::OPT_LANG." ".static::$lang."]".
                    " [-".static::OPT_SAVELANG."]".
                    " [-".static::OPT_LINE.' "'.implode(' ', static::$line).'"'."]".
                    " [-".static::OPT_OPEN.' "'.implode(' ', static::$open).'"'."]".
                    " [-".static::OPT_CLOSE.' "'.implode(' ', static::$close).'"'."]".
                    " [-".static::OPT_YANDEX." JSONFILE]".
                    " [-".static::OPT_NODRAFT."]".
                    " [-".static::OPT_INPLACE."]".
                    " ".implode('|', static::$modes)." ".
                    " FILENAME|-, where",
                "Options:",
                "-".static::OPT_PRIOR." LANGS - set languages priority",
                "-".static::OPT_LANG." LANG - selected language (this language moved to the first place in language priority list)",
                "-".static::OPT_SAVELANG." - save language in comments (by default clear)",
                "-".static::OPT_LINE." ONELINE - begin of one-line comments, may be many variants space separated",
                "-".static::OPT_OPEN." OPEN - open of multi-line comments, may be many variants space separated",
                "-".static::OPT_CLOSE." CLOSE - close of multi-line comments, may be many variants space separated",
                "-".static::OPT_YANDEX." JSONFILE - JSON-file with access to Yandex Cloud with fields: ".static::YANDEX_FOLDER_ID.
                    " AND (".static::YANDEX_OAUTH_TOKEN." OR ".static::YANDEX_IAM_TOKEN.") (required for mode ".static::MODE_TRANSLATE.", ignored in other modes)",
                "-".static::OPT_NODRAFT." - don't attach sign ".static::DRAFT." to translated comments (by default attach ".static::DRAFT.")",
                "-".static::OPT_INPLACE." - patch inplace, overwrite result of patch to source file (by default write to stdout)",
                "Modes:",
                static::MODE_DOX." - Preprocessing comments for Doxygen",
                static::MODE_MOST." - Only most priority language comments (other to clear)",
                static::MODE_TRANSLATE." - Add comments in selected language by auto-translate from most priority exists language",
                "Input:",
                "FILENAME - path to file for patching, '-' - use stdin",
            ])."\n\n");
        }

        ///EN Space separated list explode
        protected static function vlist(string $v): array {
            $list = explode(' ', $v);
            foreach ($list as &$l) $l = trim($l); unset($l);
            return $list;
        }

        ///EN Patch file/stdin content
        protected static function patch():bool {
            if ('-' === static::$filename) {
                $content = '';
                while (($buf = fread(STDIN, 128 * 1024 * 1024))) {
                    $content .= $buf;
                    usleep(50000);
                }
            } else $content = file_get_contents(static::$filename);
            if (preg_match_all(static::D.static::$reblock.static::D.'u', $content, $m, PREG_OFFSET_CAPTURE)) {
                $translate = [];//RU b => [lang, comments]
                $dbofs = 0;
                foreach ($m[0] as $b => $blockdata) {
                    $block = $blockdata[0]; $bofs = $blockdata[1]; $blen = strlen($block);
                    if (preg_match('/\n[\t ]*$/', $block, $mt)) $tail = $mt[0];
                    else $tail = '';
                    if (!preg_match_all(static::D.static::$reone.static::D.'u', $block, $mb, PREG_OFFSET_CAPTURE)) {
                        static::stderr("Logic error: Not found comments in block, skip it:\n$block");
                        continue;
                    }
                    //RU Какие языки есть в этом блоке
                    $langs = []; $ci = count($mb[0]);
                    for ($l = 0; $l <= count(static::$open); ++$l) {
                        foreach (($mb['lang'.$l] ?? []) as $i => $langdata) {
                            if (($lang = $langdata[0] ?? '')) $langs[mb_strtoupper($lang)][] = $i;
                        }
                    }
                    //RU Отбираем самый приоритетный язык
                    $slang = '';
                    foreach (static::$langs as $lang) {
                        if (isset($langs[$lang])) {
                            $slang = $lang;
                            break;
                        }
                    }
                    if (!$slang) {
                        static::stderr("Error: No language of priority list ".implode(',', static::$langs).", available only ".implode(',', array_keys($langs))." in block, skip it:\n$block");
                        continue;
                    }
                    //RU Если в блоке есть комментарий на целевом языке, перевод не нужен, пропускаем
                    if ((static::MODE_TRANSLATE === static::$mode) && ($slang === static::$lang)) continue;
                    $dcofs = 0;
                    for ($i = 0; $i < $ci; ++$i) {
                        $cofs = $mb['comment'][$i][1];
                        $clen = strlen($mb['comment'][$i][0]);
                        if (in_array($i, $langs[$slang], true)) {//RU Оставляем комментарий
                            if (static::$savelang) continue;//RU Если сохраняем и язык, то нет изменений
                            $comment = '';
                            for ($l = 0; $l <= count(static::$open); ++$l) {//RU Ищем блок с комментарием по типу
                                if (!($mb['lang'.$l][$i][0] ?? '')) continue;
                                if (static::MODE_TRANSLATE === static::$mode) {
                                    $comment = [
                                        'open' => $mb['open'.$l][$i][0],
                                        'body' => rtrim($mb['body'.$l][$i][0], " \t\r\n"),
                                        'close' => $mb['close'.$l][$i][0] ?? '',
                                    ];
                                } else $comment = $mb['open'.$l][$i][0].$mb['body'.$l][$i][0];
                                break;
                            }
                            if (!$comment) {
                                static::stderr("Logic error: Not found comment $i in block:\n$block");
                                continue;
                            }
                            //RU При переводе ничего не удаляем
                            if (static::MODE_TRANSLATE === static::$mode) {
                                $translate[$b]['lang'] = $slang;//RU С какого языка
                                $translate[$b]['comments'][] = $comment;// Комментарий нужно будет перевести
                                continue;//RU При переводе ничего не удаляем
                            }
                        } else {
                            //RU При переводе ничего не удаляем
                            if (static::MODE_TRANSLATE === static::$mode) continue;
                            //RU Удаляем комментарий сохраняя кол-во строк
                            $comment = implode("\n", array_fill(0, count(explode("\n", $mb['comment'][$i][0])), ''));
                        }
                        $newclen = strlen($comment);
                        $block = substr($block, 0, $cofs + $dcofs).$comment.substr($block, $cofs + $dcofs + $clen);
                        $dcofs += $newclen - $clen;
                    }
                    if (static::MODE_TRANSLATE !== static::$mode) {
                        $lines = explode("\n", $block); $clines = count($lines);
                        $ibeg = 0; $iend = $clines - 1;//RU Какие строки сохраняем
                        foreach ($lines as $i => &$line) {
                            if (!($line = rtrim($line))) {
                                if ($i == $ibeg) ++$ibeg;//RU Удаляем пустые строки вначале блока
                            } else $iend = $i;//RU Последняя непустая строка
                        }
                        unset($line);
                        if (($ibeg > 0) || ($iend < ($clines - 1))) {
                            $lines = array_slice($lines, $ibeg, $iend - $ibeg + 1);
                            $block = implode("\n", $lines);
                            $newclines = count($lines);
                            if ((static::MODE_DOX === static::$mode) && ($newclines < $clines)) {//RU Doxygen критичен к кол-ву строк, добавляем пустых строк
                                $block = implode("\n", array_fill(0, $clines - $newclines, '')).$block;
                            }
                        }
                        $block .= $tail;
                        $newblen = strlen($block);
                        $content = substr($content, 0, $bofs + $dbofs).$block.substr($content, $bofs + $dbofs + $blen);
                        $dbofs += $newblen - $blen;
                    }
                }
                if ((static::MODE_TRANSLATE === static::$mode) && ($translate)) {
                    $lang2texts = [];//RU Тексты для перевода
                    foreach ($translate as $b => $t) {
                        $lang = $t['lang'];
                        $lang2texts[$lang][] = implode("\n", array_column($t['comments'], 'body'));
                    }
                    //RU Все переводим
                    foreach ($lang2texts as $lang => &$texts) {
                        if (!($texts = static::yandexTranslate(static::$yandex, static::$lang, $texts, $lang))) {
                            static::stderr("Fail translate $lang to ".static::$lang);
                            return false;
                        }
                        $texts = array_column($texts, 'text');
                    }
                    unset($texts);
                    //RU Вставляем новый язык после блоков комментариев
                    $dofs = 0;
                    $draft = (static::$draft ? static::DRAFT : '');
                    foreach ($translate as $b => $t) {
                        $blockdata = $m[0][$b];
                        $block = $blockdata[0]; $blen = strlen($block); $bofs = $blockdata[1] + $dofs;
                        if (preg_match('/\n[\t ]*$/', $block, $mt)) $tail = $mt[0];
                        else $tail = '';
                        $before = substr($content, 0, $bofs);
                        if (preg_match('/(^|(?<=\n))[\t ]*$/u', $before, $mb)) $prefix = $mb[0];
                        else $prefix = false;
                        $lang = $t['lang'];
                        $text = array_shift($lang2texts[$lang]);
                        $firstComment = reset($t['comments']);
                        if (!$firstComment['close']) {//RU Однострочные комментарии
                            $lines = explode("\n", $text);
                            foreach ($lines as &$line) $line = (string)$prefix.$firstComment['open'].static::$lang.$draft.(' ' !== substr($line, 0, 1) ? ' ' : '').$line;
                            unset($line);
                            $text = implode("\n", $lines);
                        } else {//RU Многострочные комментарии
                            $text = $firstComment['open'].static::$lang.$draft.$text;
                        }
                        $text = (false !== $prefix ? "\n" : ' ').$text;
                        $content = $before.substr($block, 0, $blen - strlen($tail)).$text.$tail.substr($content, $bofs + $blen);
                        $dofs += strlen($text);
                    }
                }
            }
            if ((static::$inplace) && ('-' !== static::$filename)) file_put_contents(static::$filename, $content);
            else echo $content;
            return true;
        }

        ///EN Prepare RegExps
        protected static function prepareRE():void {
            $lchars = [];
            foreach (static::$line as $l) {
                $line[] = preg_quote($l, static::D);
                $lchars = array_unique(array_merge($lchars, mb_str_split($l, 1)));
            }
            $lchars = preg_quote(implode('', $lchars), static::D);
            $lines = 1 == count($line) ? reset($line) : implode('|', $line);
            $relang = '(?<lang0>[a-zA-Z]{2}(?![a-zA-Z]))';
            $reone = "(?<open0>$lines)$relang(?<body0>([^\n$lchars]*(?!$lines).)*)";
            if (static::$open) {
                foreach (static::$open as $l => $o) {
                    $qo = preg_quote($o, static::D);
                    $qc = preg_quote(static::$close[$l], static::D);
                    $rel = str_replace('<lang0>', '<lang'.($l+1).'>', $relang);
                    $reone .= "|(?<open".($l+1).">$qo){$rel}(?<body".($l+1).">[\\S\\s]*?(?<close".($l+1).">$qc))";
                }
            }
            $reone = "(?<comment>$reone)";
            static::$reone = $reone;
            static::$reblock = "(?:$reone(?:\n[\t ]*)?)+";
        }

        protected static function stderr(string $error):void {
            fwrite(STDERR, $error."\n\n");
        }

        protected static function yandexCheck(array $yandex): bool {
            return ($yandex[static::YANDEX_FOLDER_ID] ?? false) && (
                    ($yandex[static::YANDEX_OAUTH_TOKEN] ?? false) || ($yandex[static::YANDEX_IAM_TOKEN] ?? false)) ;
        }

        public static function yandexTranslate(array $yandex, string $toLang, array $texts, string $fromLang = ''):?array {
            if (!(static::yandexCheck($yandex))) return null;
            if (!($IAmToken = ($yandex[static::YANDEX_IAM_TOKEN] ?? ''))) {
                if (!($IAmToken = static::yandexIAmToken($yandex[static::YANDEX_OAUTH_TOKEN]))) return null;
            }
            $translations = [];
            while ($texts) {
                $chunk = []; $clen = 0;
                while ($texts) {
                    $text = array_shift($texts);
                    $len = strlen($text) + 1;
                    if (($clen + $len) > 8000) {
                        array_unshift($texts, $text);
                        break;
                    }
                    $chunk[] = $text;
                    if (count($chunk) >= 50) break;
                }
                $data = [
                    'folder_id' => $yandex[static::YANDEX_FOLDER_ID],
                    'texts' => $chunk,
                    'targetLanguageCode' => mb_strtolower($toLang),
                ];
                if ($fromLang) $data['sourceLanguageCode'] = mb_strtolower($fromLang);
                if (!($r = static::postJSON("https://translate.api.cloud.yandex.net/translate/v2/translate",
                                        $data, [CURLOPT_HTTPHEADER => ["Authorization: Bearer $IAmToken"]]))) return null;
                if (!is_array($ctranslations = ($r['translations'] ?? false))) {
                    static::stderr("Not found translations in answer\n".print_r($r, 1));
                    return null;
                }
                $translations = array_merge($translations, $ctranslations);
            }
            return $translations;
        }

        protected static function yandexIAmToken(string $OAuthToken):?string {
            if (!($r = static::postJSON("https://iam.api.cloud.yandex.net/iam/v1/tokens", ['yandexPassportOauthToken' => $OAuthToken]))) return null;
            if (!($IAmToken = $r['iamToken'] ?? '')) {
                static::stderr("Not found IAmToken in Yandex answer ".print_r($r, 1));
                return null;
            }
            return $IAmToken;
        }

        protected static function postJSON(string $url, array $vars = [], array $opts = []):?array {
            $opts[CURLOPT_POST] = true;
            if ($vars) $opts[CURLOPT_POSTFIELDS] = json_encode($vars);
            return static::curlJSON($url, $opts);
        }

        protected static function getJSON(string $url, array $vars = [], array $opts = []):?array {
            if ($vars) $url .= '?'.http_build_query($vars);
            return static::curlJSON($url, $opts);
        }

        protected static function curlJSON(string $url, array $opts = []):?array {
            if (!($c = curl_init())) return null;
            $r = null;
            do {
                $opts = array_replace(
                    [
                        CURLOPT_AUTOREFERER => true,
                        CURLOPT_FAILONERROR => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_HEADER => false,
                        CURLOPT_CONNECTTIMEOUT => 30,
                        CURLOPT_TIMEOUT => 30,
                    ],
                    $opts,
                    [CURLOPT_URL => $url]
                );
                $opts[CURLOPT_HTTPHEADER] = array_merge($opts[CURLOPT_HTTPHEADER] ?? [], ['Content-Type: application/json']);
                if (!curl_setopt_array($c, $opts)) {
                    static::stderr("curl_setopt_array: ".curl_error($c));
                    break;
                }
                $r = curl_exec($c);
                $httpCode = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
                if (200 !== $httpCode) {
                    static::stderr($url."\nHTTP $httpCode ".curl_error($c).($r ? "\n$r" : ''));
                    $r = null;
                }
            } while(0);
            curl_close($c);
            if (null === $r) return null;
            if (!is_array($ar = @json_decode($r, true))) {
                static::stderr("$url\nInvalid JSON:\n$r");
                return null;
            }
            return $ar;
        }
    };

    MLComment::run($argv);
