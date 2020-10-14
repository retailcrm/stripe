#!/usr/bin/env bash

if [[ ${WITH_XDEBUG} == "yes" ]];
then
pecl install xdebug
echo "xdebug.remote_enable = 1" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.remote_host = 172.17.0.1" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.coverage_enable = 0" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.remote_autostart = 1" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.remote_connect_back = 0" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.profiler_enable = 0" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.var_display_max_depth = -1" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.var_display_max_children = -1" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.var_display_max_data = -1" >> /usr/local/etc/php/conf.d/xdebug.ini
echo "xdebug.max_nesting_level = 500" >> /usr/local/etc/php/conf.d/xdebug.ini
mkdir /usr/local/etc/php/debug_conf.d/
echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/debug_conf.d/_xdebug.ini
ln -s /usr/local/etc/php/conf.d/* /usr/local/etc/php/debug_conf.d/
fi
