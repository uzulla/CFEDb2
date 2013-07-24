テストについて
============

## sqlite3
```
run.sh
```


## mysql
```
run_mysql.pl
```

### …えっ！？Perl！？
Test::mysqldを使っています、Harrietもつかっています。
Perlは便利ですので、みなさんPerlつかいましょう。

### Perlをつかわないテスト
```
export TEST_MYSQL_PERL_DSN='DBI:mysql:dbname=test;mysql_socket=/path/to/tmp/mysql.sock;user=root'
```
適当にMysqlを用意し、上のようなフォーマットの環境変数を設定してから`run.sh`を実行してください。