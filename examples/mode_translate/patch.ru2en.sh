#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l EN -n "///" -o "/**" -y yandex.json translate "$dir/source.cpp" > patched.ru2en.cpp
