#!/bin/bash

find . -name "*.png" | while read x; do
  new=$(echo "$x" | sed 's/\.png$/.jpg/')
  pngtopnm $x | pnmtojpeg > $new
done
