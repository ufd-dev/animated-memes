#!/bin/bash

# Check if the first argument is empty
if [ -z "$1" ]; then
    echo "Error: No argument provided."
    echo "Usage: $0 base_name_without_extension"
    echo "Example: $0 gm_good-morning"
    exit 1
fi

SRC_ANIM=$1
SRC_BASE_PATH="${SRC_ANIM%.*}"
SRC_OVERLAY="$SRC_BASE_PATH.png"
OUT="./out/${SRC_BASE_PATH##*/}.mp4"

ffmpeg \
  -i $SRC_ANIM \
  -i $SRC_OVERLAY \
  -filter_complex "overlay=0:0:format=auto,format=yuv420p" \
  -c:v libx264 -c:a aac -profile:v main -level 3.0 \
  -movflags "faststart" \
  $OUT
