#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l RU dox "$dir/source.cpp" > patched.ru.cpp
