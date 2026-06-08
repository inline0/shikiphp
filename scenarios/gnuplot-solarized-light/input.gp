# Plot a damped sine wave
set terminal pngcairo size 800,600
set output 'wave.png'

set title "Damped Sine Wave"
set xlabel "Time (s)"
set ylabel "Amplitude"
set grid

A = 1.0
decay = 0.2
f(x) = A * exp(-decay * x) * sin(2 * pi * x)

set xrange [0:10]
set samples 500

plot f(x) with lines lw 2 lc rgb "#cc0000" title "f(x)", \
     A * exp(-decay * x) with lines dt 2 title "envelope"
