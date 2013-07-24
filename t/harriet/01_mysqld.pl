$ENV{TEST_MYSQL_PERL_DSN} ||= do {
    require Test::mysqld;
    my $mysqld = Test::mysqld->new(
        my_cnf => {
            'skip-networking' => '', # no TCP socket
        }
    ) or die $Test::mysqld::errstr;
    $HARRIET_GUARDS::MYSQLD = $mysqld;
    $dsn = $mysqld->dsn;
    #DBI:mysql:dbname=test;mysql_socket=/path/to/tmp/mysql.sock;user=root
};

