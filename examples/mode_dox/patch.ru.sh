#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l RU -n "///" -o "/**" -c "*/"  dox "$dir/source.cpp" > patched.ru.cpp
