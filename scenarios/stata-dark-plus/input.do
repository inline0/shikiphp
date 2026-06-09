clear all
set more off

use "data/survey.dta", clear

gen age_group = .
replace age_group = 1 if age < 30
replace age_group = 2 if age >= 30 & age < 60

summarize income, detail
regress income age i.education

foreach var in income age {
    display "mean of `var'"
    summarize `var'
}
