#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l RU -s most "$dir/source.cpp" > patched.ru.cpp
