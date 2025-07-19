#!/bin/bash

cd /var/www/mikrotik-ppp-billing

while true
do
  php artisan ppp:suspend-unpaid
  php artisan ppp:restore-paid
  sleep 10
done
