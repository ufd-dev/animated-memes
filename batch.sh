#!/bin/bash

shopt -s nocaseglob
for file in src/*.{gif,mov,mp4,webp}; do
    [ -e "$file" ] || continue

    ./addoverlay.sh "$file" none

    # create versions with the logo in each color and corner
    for color in bl wh ; do
      for corner in tl tr bl br; do
          ./addoverlay.sh "$file" "$corner" "$color"
      done
    done
done
shopt -u nocaseglob

