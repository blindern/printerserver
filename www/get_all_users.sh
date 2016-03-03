#!/bin/bash

## get UID limit ##
l=$(grep "^UID_MIN" /etc/login.defs)

## use awk to print if UID >= $UID_LIMIT ##
awk -F':' -v "limit=${l##UID_MIN}" '{ if ( $3 >= limit ) print $1}' /etc/passwd
