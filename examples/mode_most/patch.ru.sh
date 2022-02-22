#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l RU most "$dir/source.cpp" > patched.ru.cpp
