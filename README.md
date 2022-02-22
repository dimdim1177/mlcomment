### Multi-language comment

The script is designed for easy extraction and documentation of code with multilingual comments.

### Usage

First time, you must install Linux and PHP7 :) Then, clone repo and use mlcomment.php as binary, or you can call it but php mlcomment.php.


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

Usage: mlcomment.php [Options] dox|most  FILENAME|-, where

Options:
- -p LANGS - set languages priority
- -l LANG - selected language (this language moved to the first place in language priority list)
- -s - save language in comments (by default clear)
- -n ONELINE - begin of one-line comments, may be many variants space separated
- -o OPEN - open of multi-line comments, may be many variants space separated
- -c CLOSE - close of multi-line comments, may be many variants space separated

Modes:
- dox - Preprocessing comments for Doxygen
- most - Only most priority language comments (other to clear)

FILENAME - path to file for patching, '-' - use stdin
Output always to stdout

For example:

`mlcomment.php -l RU -n "//" -o "/*" -c "*/" dox ./source.cpp > patched.ru.cpp`
