#!/usr/bin/perl
use strict;
use warnings;

# Compute factorial
sub factorial {
    my ($n) = @_;
    return 1 if $n <= 1;
    return $n * factorial($n - 1);
}

my @nums = (1 .. 5);
my %config = ( debug => 1, retries => 3 );

my $name = "World";
print "Hello, $name!\n";

foreach my $n (@nums) {
    printf "%d! = %d\n", $n, factorial($n);
}

my @evens = grep { $_ % 2 == 0 } @nums;
print "Evens: @evens\n" if @evens;
