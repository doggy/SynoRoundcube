#!/bin/sh
sed -i 's/"{0}"/Mail\ Server/' strings/*
sed -i 's/{0}/Mail\ Server/' strings/*

cat strings/Czech_UI_.txt >> csy/strings
cat strings/Danish_UI_.txt >> dan/strings
cat strings/Dutch_UI_.txt >> nld/strings
cat strings/English_UI_.txt >> enu/strings
cat strings/French_UI_.txt >> fre/strings
cat strings/German_UI_.txt >> ger/strings
cat strings/Hungarian_UI_.txt >> hun/strings
cat strings/Italian_UI_.txt >> ita/strings
cat strings/Japanese_UI_.txt >> jpn/strings
cat strings/Korean_UI_.txt >> krn/strings
cat strings/Norwegian_UI_.txt >> nor/strings
cat strings/Polish_UI_.txt >> plk/strings
cat strings/Portuguese\ \(Brazil\)_UI_.txt >> ptb/strings
cat strings/Portuguese\ \(European\)_UI_.txt >> ptg/strings
cat strings/Russian_UI_.txt >> rus/strings
cat strings/Simplified\ Chinese_UI_.txt >> chs/strings
cat strings/Spanish_UI_.txt >> spn/strings
cat strings/Swedish_UI_.txt >> sve/strings
cat strings/Traditional\ Chinese_UI_.txt >> cht/strings
cat strings/Turkish_UI_.txt >> trk/strings



