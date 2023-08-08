#!/bin/bash

# newgrp instructors # execs so lose context
ps -A | grep ' 'cr4bd-ohq -q && exit 0

if [ "$(ls -t source | head -1)" -nt ohq ]; then dub build -b release >>buildlog 2>>buildlog; fi
date >> logs/runlog
nohup bash restart_on_segfault.sh ./cr4bd-ohq >>logs/runlog 2>>logs/runlog </dev/null &
