@Echo Off
IF %1.==. GOTO No1
IF %2.==. GOTO No2
Set gsutiluri=%1
Set gsutildir=%2
cd /d "C:\Python27"
python "C:\gsutil\gsutil" -m cp %gsutiluri% %gsutildir%
GOTO End1
:No1
  Exit
GOTO End1
:No2
  Exit
GOTO End1
:End1
  Exit