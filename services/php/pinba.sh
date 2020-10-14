#!/usr/bin/env bash

if [[ ${WITH_PINBA} == "yes" ]];
then

wget https://github.com/tony2001/pinba_extension/archive/master.zip
unzip master.zip
rm master.zip
cd pinba_extension-master
phpize
./configure --enable-pinba
make
make install
cd ..
cp pinba_extension-master/modules/pinba.so /usr/local/lib/php/extensions/pinba.so
rm -R pinba_extension-master
echo "extension=/usr/local/lib/php/extensions/pinba.so" >> /usr/local/etc/php/conf.d/pinba.ini
echo "pinba.enabled=1" >> /usr/local/etc/php/conf.d/pinba.ini
echo "pinba.server=pinba:30002" >> /usr/local/etc/php/conf.d/pinba.ini

fi




