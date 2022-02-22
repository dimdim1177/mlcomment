#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l EN -s most "$dir/source.cpp" > patched.en.cpp
