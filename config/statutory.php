<?php

/*
|--------------------------------------------------------------------------
| Malaysian statutory contribution & tax parameters
|--------------------------------------------------------------------------
| These change with each Budget / gazette. Update here (not in code) and have
| an accountant confirm results against the official KWSP, PERKESO and LHDN
| calculators before running live payroll.
| Last reviewed: Nov 2026 (YA2025 PCB rules).
*/

return [

    'epf' => [
        // Third Schedule wage bands: RM20 steps up to RM5,000, RM100 steps up to RM20,000;
        // contribution = rate × band upper limit, rounded UP to the next ringgit.
        // Above RM20,000: rate × actual wages, rounded up to the next ringgit.
        'band_small' => 20,
        'band_small_limit' => 5000,
        'band_large' => 100,
        'band_large_limit' => 20000,
        'min_wage' => 10, // wages up to RM10: no contribution
        'rates' => [
            // citizen / PR below 60
            'local_below_60' => ['employee' => 11.0, 'employer_low' => 13.0, 'employer_high' => 12.0, 'employer_threshold' => 5000],
            // citizen / PR aged 60+
            'local_60_plus' => ['employee' => 0.0, 'employer_low' => 4.0, 'employer_high' => 4.0, 'employer_threshold' => 5000],
            // non-Malaysian workers (mandatory from Oct 2025 wages)
            'foreigner' => ['employee' => 2.0, 'employer_low' => 2.0, 'employer_high' => 2.0, 'employer_threshold' => 5000],
        ],
    ],

    'socso' => [
        'wage_ceiling' => 6000,
        // Category 1 = Employment Injury + Invalidity (below 60)
        // Category 2 = Employment Injury only (60+, and foreign workers): employer only
        'rates' => [
            'cat1' => ['employee' => 0.5, 'employer' => 1.75],
            'cat2' => ['employee' => 0.0, 'employer' => 1.25],
        ],
        // Irregular low-wage bands from the First Schedule: [upper wage, employer, employee] (category 1)
        'low_bands_cat1' => [
            [30, 0.40, 0.10], [50, 0.70, 0.20], [70, 1.10, 0.30], [100, 1.50, 0.40],
            [140, 2.10, 0.60], [200, 2.95, 0.85], [300, 4.35, 1.25],
        ],
        'low_bands_cat2' => [
            [30, 0.30, 0.0], [50, 0.50, 0.0], [70, 0.80, 0.0], [100, 1.10, 0.0],
            [140, 1.50, 0.0], [200, 2.10, 0.0], [300, 3.10, 0.0],
        ],
    ],

    'eis' => [
        'wage_ceiling' => 6000,
        'employee' => 0.2,
        'employer' => 0.2,
        'max_age' => 60, // not payable from age 60
        // [upper wage, employer, employee]
        'low_bands' => [
            [30, 0.05, 0.05], [50, 0.10, 0.10], [70, 0.15, 0.15], [100, 0.20, 0.20],
            [140, 0.25, 0.25], [200, 0.35, 0.35], [300, 0.50, 0.50],
        ],
    ],

    'pcb' => [
        'individual_relief' => 9000,
        'spouse_relief' => 4000,         // spouse with no income
        'child_relief' => 2000,          // per qualifying child
        'disabled_relief' => 7000,       // individual with disability
        'disabled_spouse_relief' => 6000,
        'epf_relief_cap' => 4000,        // EPF portion of the life-insurance/EPF relief
        'minimum_deduction' => 10,       // PCB below RM10 is not deducted
        'non_resident_rate' => 30,
        // Chargeable income brackets: [from P, M, R%, B (cat 1 & 3), B (cat 2)]
        // B already includes the RM400 individual / RM800 individual+spouse rebate for P ≤ 35,000.
        'brackets' => [
            [5001, 5000, 1, -400, -800],
            [20001, 20000, 3, -250, -650],
            [35001, 35000, 6, 600, 600],
            [50001, 50000, 11, 1500, 1500],
            [70001, 70000, 19, 3700, 3700],
            [100001, 100000, 25, 9400, 9400],
            [400001, 400000, 26, 84400, 84400],
            [600001, 600000, 28, 136400, 136400],
            [2000001, 2000000, 30, 528400, 528400],
        ],
    ],

];
