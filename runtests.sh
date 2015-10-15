#!/bin/sh
phpunit --testsuite 'Jade tests' --process-isolation "$@"
