#!/bin/bash

usage() {
  echo "addwatermark.sh <source-file> <position none|tl|tr|bl|br> [<color bl|wh>]"
}

src=$1
corner=$2
color=$3

# validate source file
if [[ -z "$src" ]]; then
  echo "Error: Missing filename." >&2
  usage
  exit 1
elif [[ ! -f "$src" ]]; then
  echo "Error: File '$src' does not exist." >&2
  usage
  exit 1
fi

declare -A corner_to_position=(
  ["tl"]="20:20"
  ["tr"]="main_w-overlay_w-10:20"
  ["bl"]="20:main_h-overlay_h-20"
  ["br"]="main_w-overlay_w-10:main_h-overlay_h-20"
)

# validate corner and translate to position/outfile
if [[ "$corner" == "none" ]]; then
  inputs=(
    -i "$src"
  )
  extra_filters=""
  out_suffix=""
elif [[ -n "${corner_to_position[$corner]}" ]]; then
  # validate color
  if [[ "$color" != "bl" && "$color" != "wh" ]]; then
    echo "Error: Color must be 'bl' or 'wh'. Received: $color"
    usage
    exit 1
  fi
  inputs=(
    -i "$src"
    -i "overlay/logoTM-${color}.png"
  )
  extra_filters=",overlay=${corner_to_position[$corner]}"
  out_suffix="_${color}_${corner}"
else
    echo "Error: Invalid corner '$corner'." >&2
    usage
    exit 1
fi

settings=(
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

base_filename=$(basename "${src%.*}")
outfile="out/${base_filename}${out_suffix}.mp4"

mkdir -p out
ffmpeg "${inputs[@]}" "${settings[@]}" "$outfile"

