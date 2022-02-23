#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l EN -n "///" -o "/**" -c "*/" dox "$dir/source.cpp" > patched.en.cpp
