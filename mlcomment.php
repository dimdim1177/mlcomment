#!/usr/bin/php
<?php

    class MLComment {
        //RU Ключ командной строки для указания приоритетов языков
        //EN Command line option for set languages priority
        const OPT_PRIOR = 'p';

        //RU Ключ командной строки для выбора языка (этот язык встает на первое место в приоритетах)
        //EN Command line option for select language (this language moved to the first place in priority)
        const OPT_LANG = 'l';

        //RU По умолчанию название языка удаляется после обработки, этот ключ позволяет его оставить
        //EN By default language suffix in comment removed, this option could save it
        const OPT_SAVELANG = 's';

        //RU Как начинаются однострочные комментарии, можно несколько вариантов через пробел
        //EN Begin of one-line comments, available space separated list
        const OPT_LINE = 'n';

        //RU Как открываются многострочные комментарии, можно несколько вариантов через пробел
        //EN Open of multi-line comments, available space separated list
        const OPT_OPEN = 'o';

        //RU Как закрываются многострочные комментарии, можно несколько вариантов через пробел
        //EN Close of multi-line comments, available space separated list
        const OPT_CLOSE = 'c';

        //RU Вывод справки
        //EN Show help
        const OPT_HELP = 'h';

        //RU Предобработка файла для Doxygen
        //EN Preprocessing comments for Doxygen
        const MODE_DOX = 'dox';

        //RU Оставить комментарии только на самом приоритетном языке
        //EN Only most priority language comments
        const MODE_MOST = 'most';

        //RU Все режимы
        //EN All modes
        protected static $modes = [ self::MODE_DOX, self::MODE_MOST ];

        //RU Приоритет языков по умолчанию
        //EN Priority of languages by default
        protected static $langs = [ 'EN', 'RU', 'ES', 'FR', 'IT' ];

        //RU Выбранный самый приоритетный язык
        //EN Selected most priority language
        protected static $lang = '';

        //RU Начало однострочного комментария
        //EN Begin of one-lime comment
        protected static $line = ['//'];

        //RU Открытие многострочного комментария
        //EN Open of multi-line comment
        protected static $open = ['/*'];

        //RU Закрытие многострочного комментария
        //EN Close of multi-line comment
        protected static $close = ['*/'];

        //RU Сохранять ли язык в комментариях
        //EN Save language in comments
        protected static $savelang = false;

        //RU Выбранный режим
        //EN Selected mode
        protected static $mode = '';

        //RU Файл для обработки, '-' - stdin
        //EN File for patch, '-' - stdin
        protected static $filename = '';

        //RU Разделительный символ для паттера регулярки
        //EN Delimiter symbol of regexp pattern
        const D = '/';

        //RU RegExp для извлечения одного комментария с языками
        //EN RegExp for one comment with languages
        protected static $reone;

        //RU RegExp для блока комментариев на разных языках (из которых будет выбирать приоритетный)
        //EN RegExp for block of multi-language comments (most priority language will be selected)
        protected static $reblock;

        public static function run(array $argv):bool {
            if (!static::parseArgs($argv)) return false;
            static::prepareRE();
            return static::patch();
        }

        //RU Разбор аргументов командной строки
        //EN Parse command line arguments
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
            if ($errors) {
                static::stderr(implode("\n", $errors));
                static::usage($argv, true);
                return false;
            }
            return true;
        }

        //RU Вывод справки о параметрах
        //EN Print usage of script
        public static function usage($argv, bool $tostderr = false):void {
            fwrite($tostderr ? STDERR : STDOUT, implode("\n", [
                "Usage: ".basename($argv[0]).
                    " [-".static::OPT_PRIOR.' "'.implode(' ', static::$langs).'"'."]".
                    " [-".static::OPT_LANG." ".static::$lang."]".
                    " [-".static::OPT_SAVELANG."]".
                    " [-".static::OPT_LINE.' "'.implode(' ', static::$line).'"'."]".
                    " [-".static::OPT_OPEN.' "'.implode(' ', static::$open).'"'."]".
                    " [-".static::OPT_CLOSE.' "'.implode(' ', static::$close).'"'."]".
                    " ".implode('|', static::$modes)." ".
                    " FILENAME|-, where",
                "-".static::OPT_PRIOR." LANGS - set languages priority",
                "-".static::OPT_LANG." LANG - selected language (this language moved to the first place in language priority list)",
                "-".static::OPT_SAVELANG." - save language in comments (by default clear)",
                "-".static::OPT_LINE." ONELINE - begin of one-line comments, may be many variants space separated",
                "-".static::OPT_OPEN." OPEN - open of multi-line comments, may be many variants space separated",
                "-".static::OPT_CLOSE." CLOSE - close of multi-line comments, may be many variants space separated",
                "Modes:",
                static::MODE_DOX." - Preprocessing comments for Doxygen",
                static::MODE_MOST." - Only most priority language comments (other to clear)",
                "FILENAME - path to file for patching, '-' - use stdin",
                "Output always to stdout",
            ])."\n\n");
        }

        //EN Space separated list explode
        protected static function vlist(string $v): array {
            $list = explode(' ', $v);
            foreach ($list as &$l) $l = trim($l); unset($l);
            return $list;
        }

        //EN Patch file/stdin content
        protected static function patch():bool {
            if ('-' === static::$filename) {
                $content = '';
                while (($buf = fread(STDIN, 128 * 1024 * 1024))) {
                    $content .= $buf;
                    usleep(50000);
                }
            } else $content = file_get_contents(static::$filename);
            if (preg_match_all(static::D.static::$reblock.static::D.'u', $content, $m, PREG_OFFSET_CAPTURE)) {
                $dbofs = 0;
                foreach ($m[0] as $blockdata) {
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
                    $dcofs = 0;
                    for ($i = 0; $i < $ci; ++$i) {
                        $cofs = $mb['comment'][$i][1];
                        $clen = strlen($mb['comment'][$i][0]);
                        if (in_array($i, $langs[$slang], true)) {//RU Оставляем комментарий
                            if (static::$savelang) continue;//RU Есл сохраняем и язык, то нет изменений
                            $comment = '';
                            for ($l = 0; $l <= count(static::$open); ++$l) {//RU Ищем блок с комментарием по типу
                                if (!($mb['lang'.$l][$i][0] ?? '')) continue;
                                $comment = $mb['open'.$l][$i][0].$mb['body'.$l][$i][0];
                                break;
                            }
                            if (!$comment) {
                                static::stderr("Logic error: Not found comment $i in block:\n$block");
                                continue;
                            }
                        } else {
                            //RU Удаляем комментарий сохраняя кол-во строк
                            $comment = implode("\n", array_fill(0, count(explode("\n", $mb['comment'][$i][0])), ''));
                        }
                        $newclen = strlen($comment);
                        $block = substr($block, 0, $cofs + $dcofs).$comment.substr($block, $cofs + $dcofs + $clen);
                        $dcofs += $newclen - $clen;
                    }
                    $lines = explode("\n", $block); $clines = count($lines);
                    $ibeg = 0; $iend = $clines - 1;//RU Какие строки сохраняем
                    foreach ($lines as $i => &$line) {
                        if (!($line = rtrim($line))) {
                            if ($i == $ibeg) ++$ibeg;//RU Удаляем пустые строки вначале блока
                        } else $iend = $i;//RU Последня непустая строка
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
            echo $content;
            return true;
        }

        //EN Prepare RegExps
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
                    $reone .= "|(?<open".($l+1).">$qo){$rel}(?<body".($l+1).">[\\S\\s]*?$qc)";
                }
            }
            $reone = "(?<comment>$reone)";
            static::$reone = $reone;
            static::$reblock = "(?:$reone(?:\n[\t ]*)?)+";
        }

        protected static function stderr(string $error):void {
            fwrite(STDERR, $error."\n\n");
        }
    };

    MLComment::run($argv);

    //TODO
    // - set language (find all comment without language and set language for it)
    // - flag of draft ! , when //EN! mean draft comment (code changed, but comment not; autotranslating; must be review and so on)

