#!/bin/bash
scanimage -d '04a9:18a2' --progress --resolution 600 --mode Lineart -x 210 -y 297 | pnmtops -imagewidth 11.3 -imageheight 11.7 -nocenter | ps2pdf - /home/pi/CanoPyScannerGUI/scanner/test.pdf
