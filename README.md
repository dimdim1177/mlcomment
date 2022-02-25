## Multi-language comments

The script is designed for easy extraction and documentation of code with multilingual comments.
Developed and testing under Debian 11 / PHP 7.4.

### Usage

First time, you must install Linux and PHP7 :) Then, clone repo and use `mlcomment.php` as binary, or you can call it by `php mlcomment.php`.

`mlcomment.php [options] dox|most  FILENAME|-`

For example:

`mlcomment.php -l RU dox ./source.cpp > patched.ru.cpp`

### How it works

In code, after start comment coder must place two-letter language code.

For example:

```
int Hello = 0;//RU< Переменная Привет //EN< Variable Hello

//RU Переменная Мир
//EN Variable World
char World;
```

From this code by this script you can extract Russian version:

```
int Hello = 0;//< Переменная Привет

// Переменная Мир
char World;
```
Or English version:
```
int Hello = 0;//< Variable Hello

// Variable World
char World;
```

If no comment in selected language, used next available language by priority, default priority of languages:  EN, RU, ES, FR, IT

### Full avaliable options

Usage: mlcomment.php [Options] dox|most|translate  FILENAME|-, where

Options:
- -p LANGS - set languages priority
- -l LANG - selected language (this language moved to the first place in language priority list)
- -s - save language in comments (by default clear)
- -n ONELINE - begin of one-line comments, may be many variants space separated
- -o OPEN - open of multi-line comments, may be many variants space separated
- -c CLOSE - close of multi-line comments, may be many variants space separated
- -y JSONFILE - JSON-file with access to Yandex Cloud with fields: folder_id AND (oauth_token OR iam_token) (required for mode translate, ignored in other modes)
- -d - don't attach sign ~ to translated comments (by default attach ~)
- -i - patch inplace, overwrite result of patch to source file (by default write to stdout)

Modes:
- dox - Preprocessing comments for Doxygen
- most - Only most priority language comments (other to clear)
- translate - Add comments in selected language by auto-translate from most priority exists language

Input: FILENAME - path to file for patching, '-' - use stdin

For example:

`mlcomment.php -l RU -n "//" -o "/*" -c "*/" dox ./source.cpp > patched.ru.cpp`

### Examples

#### mode_dox (patch for Doxygen)

Usage of mode 'dox' - patch comments for auto-documenting by Doxygen.
Please, look to scripts and its results.

#### mode_most (extract one language comments)

Scripts extract one most priority language comment per block.
Please, look to scripts and its results.

#### mode_most_savelang (extract one language comments, but save language code)

Scripts extract one most priority language comment per block, but save 2-letter language codes in it. Usable, for add translates, when they not exists.
Please, look to scripts and its results.

#### mode_translate (add other language comments auto-translated by Yandex)

Scripts search blocks, where absent comments in selected language and add to block auto-translated by Yandex comments in selected language.

This block
```
int Hello = 0;///RU< Переменная Привет

///RU Переменная Мир
///RU
///RU Подробнейшее описание с деталями
char World;
```
Patched to this state:
```
int Hello = 0;///RU< Переменная Привет ///EN~< Variable Hello

///RU Переменная Мир
///RU
///RU Подробнейшее описание с деталями
///EN~ Variable World
///EN~
///EN~ Detailed description with details
char World;
```

Sign ~ mark auto-transled comments for checking by user and manually clear mark after review.
 
Please, look to scripts and its results.

For use this mode you must has active Yandex Cloud account:
1. Register at https://cloud.yandex.ru/
2. Get FOLDER_ID from this page https://console.cloud.yandex.ru/ (default folder created with account)
3. Create billing account at https://console.cloud.yandex.ru/billing (you must attach bank card, but it is free)
4. Activate trial mode
4. Get OAuth token, see here https://cloud.yandex.ru/docs/iam/operations/iam-token/create
5. Then you can use OAuth token or create one day usage IAm Token  

Then create yandex.json file for -y option, this format
```
{
  "folder_id": "FOLDER_ID",
  "iam_token": "IAM_TOKEN"
}
```
Or this format, both usable (of cource, you must fill ID and TOKEN)
```
{
  "folder_id": "FOLDER_ID",
  "oauth_token": "OAUTH_TOKEN"
}
```
