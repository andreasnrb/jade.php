#!/bin/sh
phpunit --testsuite 'Phaded tests' --process-isolation "$@"
