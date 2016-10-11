@Echo Off
cd /d "C:\Python27"
python "C:\gsutil\gsutil" -m cp gs://pubsite_prod_rev_12173192579434914685/earnings/earnings_201601_2573303232889037-7.zip C:\gsutil\download\report\UUUU
pause
