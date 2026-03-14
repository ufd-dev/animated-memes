#!/bin/bash

process_video() {
    local input="$1"
    local out_suffix="$2"
    local extra_filters="$3"
    shift 3 # we only want leftovers in $@ for use below

    local filename="${input%.*}"
    filename=$(basename "$filename")
    local outfile="out/${filename}${out_suffix}.mp4"
    echo "Writing ${outfile}"

    local inputs=(
      -i "$input"
      "$@"
    )

    local settings=(
      -n                         # do not overwrite
      -brand mp42                # avoids quicktime (early mp4) container
      -c:v libx264               # recommended encoding
      -crf 20                    # constant rate facotr; default 23; lower is higher quality
      -an -sn -dn                # strip audio, subtitle, and data tracks
      -profile:v main -level 3.0 # compatibility with more devices
      -movflags "faststart"      # lets video play sooner on the web

      # Force a keyframe every 2 seconds regardless of input FPS; better for quality & looping
      -force_key_frames "expr:gte(t,n_forced*2)"

      # format converts from paletted files like GIFs
      # scale to set a max height of 720px
      # extra_filters to receive watermark args from the caller
      -filter_complex "[0:v]format=yuv420p,scale=-2:min(ih\,720)${extra_filters}"
    )

    ffmpeg "${inputs[@]}" "${settings[@]}" "$outfile"
}

# Format: "suffix:coordinates"
# Left offset: 20 | Top offset: 20 | Bottom offset: 20 | Right offset: 10
POSITIONS=(
    "tl:20:20"
    "tr:main_w-overlay_w-10:20"
    "bl:20:main_h-overlay_h-20"
    "br:main_w-overlay_w-10:main_h-overlay_h-20"
)

mkdir -p out

shopt -s nocaseglob
for file in src/*.{gif,mov,mp4,webp}; do
    [ -e "$file" ] || continue

    # reencoded with a max heigh of 720px
    process_video "$file" "" ""

    # create versions with the logo in each color and corner
    for color in black white ; do
      for pos in "${POSITIONS[@]}"; do
          suffix="${pos%%:*}"
          coords="${pos#*:}"
          process_video "$file" "_${color}_${suffix}" ",overlay=${coords}" -i "overlay/logoTM-${color}.png"
      done
    done
done
shopt -u nocaseglob

