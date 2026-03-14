#!/bin/bash

for F in ./src/* ; do 
  SRC_BASE_PATH="${F%.*}"
  OUT_BASE="./out/${SRC_BASE_PATH##*/}"

  REENCODED_OUT="${OUT_BASE}.mp4"
  if [ -e "$REENCODED_OUT" ]; then
    continue
  fi

  ffmpeg \
    -n \
    -i "$F" \
    -c:v libx264 -c:a aac -profile:v main -level 3.0 \
    -movflags "faststart" \
    "$REENCODED_OUT"

  for STYLE in black-logo white-logo ; do 
    for LOC in tl tr br ; do 
      SUFFIX="$STYLE-$LOC"
      SRC_OVERLAY="./overlay/overlay-$SUFFIX.png"
      ffmpeg \
        -n \
        -i $F \
        -i $SRC_OVERLAY \
        -filter_complex "overlay=0:0:format=auto,format=yuv420p" \
        -c:v libx264 -c:a aac -profile:v main -level 3.0 \
        -movflags "faststart" \
        "${OUT_BASE}-$SUFFIX.mp4"
    done
  done
done
