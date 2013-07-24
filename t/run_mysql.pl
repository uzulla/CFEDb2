#!/usr/bin/env perl
# Q: why testing by perl?
# A: because, i like perl!.

use Harriet;
Harriet->new('harriet')->load_all();
print `./run.sh`;

# also ...
# $ harriet ./harriet
# copy `export TEST_MYSQL_PERL_DSN='DBI:mysql:dbname=test;mysql_socket=/path/to/tmp/mysql.sock;user=root'`
#
# open another term.
# $ export TEST_MYSQL_PERL_DSN='DBI:mysql:dbname=test;mysql_socket=/path/to/tmp/mysql.sock;user=root'
# $ run.sh
#
# C-c harriet.
