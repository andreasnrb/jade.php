#!/bin/sh
./vendor/bin/phpunit --testsuite 'Jade tests' --process-isolation "$@"
