#!/bin/bash

"$*"
ecode="$?"
while [ "$ecode" -eq 11 ] || [ "$ecode" -eq 139 ]
do
    echo "Signal $ecode at" $(date)
    "$*"
    ecode="$?"
done
