#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l EN dox "$dir/source.cpp" > patched.en.cpp
