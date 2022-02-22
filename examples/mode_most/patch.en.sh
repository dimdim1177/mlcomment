#!/bin/bash

   dir=$(dirname $0)
   "$dir/../../mlcomment.php" -l EN most "$dir/source.cpp" > patched.en.cpp
