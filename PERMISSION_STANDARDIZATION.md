# Permission Standardization Mapping

## Standard Format: singular + kebab-case

Examples:
- `material-categories` → `material-category`
- `purchase-invoices` → `purchase-invoice`
- `manpower` → `man-power`
- `tools-and-equipment` → `tool-and-equipment`

---

## Mapping Table (Old → New)

| # | Old Permission | New Permission | Status |
|---|---------------|----------------|--------|
| 1 | activities create | activity create | |
| 2 | activities delete | activity delete | |
| 3 | activities edit | activity edit | |
| 4 | activities export | activity export | |
| 5 | activities manage | activity manage | |
| 6 | activities show | activity show | |
| 7 | allowanceoption create | allowanceoption create | OK (compound word) |
| 8 | allowanceoption delete | allowanceoption delete | OK (compound word) |
| 9 | allowanceoption edit | allowanceoption edit | OK (compound word) |
| 10 | allowanceoption manage | allowanceoption manage | OK (compound word) |
| 11 | announcement create | announcement create | OK |
| 12 | announcement delete | announcement delete | OK |
| 13 | announcement edit | announcement edit | OK |
| 14 | announcement manage | announcement manage | OK |
| 15 | awardtype create | awardtype create | OK |
| 16 | awardtype delete | awardtype delete | OK |
| 17 | awardtype edit | awardtype edit | OK |
| 18 | awardtype manage | awardtype manage | OK |
| 19 | bug comments create | bug comments create | OK (plural noun) |
| 20 | bug comments delete | bug comments delete | OK (plural noun) |
| 21 | bugstage create | bugstage create | OK |
| 22 | bugstage delete | bugstage delete | OK |
| 23 | bugstage edit | bugstage edit | OK |
| 24 | bugstage manage | bugstage manage | OK |
| 25 | bugstage show | bugstage show | OK |
| 26 | chartofaccount create | chartofaccount create | OK (compound) |
| 27 | chartofaccount delete | chartofaccount delete | OK (compound) |
| 28 | chartofaccount edit | chartofaccount edit | OK (compound) |
| 29 | chartofaccount manage | chartofaccount manage | OK (compound) |
| 30 | chartofaccount show | chartofaccount show | OK (compound) |
| 31 | company contribution create | company contribution create | OK |
| 32 | company contribution delete | company contribution delete | OK |
| 33 | company contribution edit | company contribution edit | OK |
| 34 | company contribution manage | company contribution manage | OK |
| 35 | daily-consumption create | daily-consumption create | OK |
| 36 | daily-consumption delete | daily-consumption delete | OK |
| 37 | daily-consumption edit | daily-consumption edit | OK |
| 38 | daily-consumption export | daily-consumption export | OK |
| 39 | daily-consumption manage | daily-consumption manage | OK |
| 40 | daily-consumption show | daily-consumption show | OK |
| 41 | daily-progress-report create | daily-progress-report create | OK |
| 42 | daily-progress-report delete | daily-progress-report delete | OK |
| 43 | daily-progress-report edit | daily-progress-report edit | OK |
| 44 | daily-progress-report export | daily-progress-report export | OK |
| 45 | daily-progress-report manage | daily-progress-report manage | OK |
| 46 | daily-progress-report show | daily-progress-report show | OK |
| 47 | general-transfer create | general-transfer create | OK |
| 48 | general-transfer delete | general-transfer delete | OK |
| 49 | general-transfer edit | general-transfer edit | OK |
| 50 | general-transfer export | general-transfer export | OK |
| 51 | general-transfer manage | general-transfer manage | OK |
| 52 | general-transfer show | general-transfer show | OK |
| 53 | grn create | grn create | OK (acronym) |
| 54 | grn delete | grn delete | OK (acronym) |
| 55 | grn edit | grn edit | OK (acronym) |
| 56 | grn export | grn export | OK (acronym) |
| 57 | grn manage | grn manage | OK (acronym) |
| 58 | grn show | grn show | OK (acronym) |
| 59 | indent create | indent create | OK |
| 60 | indent delete | indent delete | OK |
| 61 | indent edit | indent edit | OK |
| 62 | indent export | indent export | OK |
| 63 | indent manage | indent manage | OK |
| 64 | indent show | indent show | OK |
| 65 | lead call create | lead call create | OK |
| 66 | lead call delete | lead call delete | OK |
| 67 | lead call edit | lead call edit | OK |
| 68 | lead email create | lead email create | OK |
| 69 | lead task create | lead task create | OK |
| 70 | lead task delete | lead task delete | OK |
| 71 | lead task edit | lead task edit | OK |
| 72 | leadstages create | leadstages create | OK |
| 73 | leadstages delete | leadstages delete | OK |
| 74 | leadstages edit | leadstages edit | OK |
| 75 | leadstages manage | leadstages manage | OK |
| 76 | leave approver manage | leave approver manage | OK |
| 77 | leavetype create | leavetype create | OK |
| 78 | leavetype delete | leavetype delete | OK |
| 79 | leavetype edit | leavetype edit | OK |
| 80 | leavetype manage | leavetype manage | OK |
| 81 | letter certificate manage | letter certificate manage | OK |
| 82 | letter joining manage | letter joining manage | OK |
| 83 | letter noc manage | letter noc manage | OK |
| 84 | loanoption create | loanoption create | OK |
| 85 | loanoption delete | loanoption delete | OK |
| 86 | loanoption edit | loanoption edit | OK |
| 87 | loanoption manage | loanoption manage | OK |
| 88 | machinery create | machinery create | OK |
| 89 | machinery delete | machinery delete | OK |
| 90 | machinery edit | machinery edit | OK |
| 91 | machinery export | machinery export | OK |
| 92 | machinery manage | machinery manage | OK |
| 93 | machinery show | machinery show | OK |
| 94 | machinery transfer | machinery transfer | OK |
| 95 | **machinery-categories create** | **machinery-category create** | ⚠️ PLURAL → SINGULAR |
| 96 | **machinery-categories delete** | **machinery-category delete** | ⚠️ PLURAL → SINGULAR |
| 97 | **machinery-categories edit** | **machinery-category edit** | ⚠️ PLURAL → SINGULAR |
| 98 | **machinery-categories export** | **machinery-category export** | ⚠️ PLURAL → SINGULAR |
| 99 | **machinery-categories manage** | **machinery-category manage** | ⚠️ PLURAL → SINGULAR |
| 100 | **machinery-categories show** | **machinery-category show** | ⚠️ PLURAL → SINGULAR |
| 101 | machinery-dpr create | machinery-dpr create | OK (acronym) |
| 102 | machinery-dpr delete | machinery-dpr delete | OK (acronym) |
| 103 | machinery-dpr edit | machinery-dpr edit | OK (acronym) |
| 104 | machinery-dpr export | machinery-dpr export | OK (acronym) |
| 105 | machinery-dpr manage | machinery-dpr manage | OK (acronym) |
| 106 | machinery-dpr show | machinery-dpr show | OK (acronym) |
| 107 | **manpower create** | **man-power create** | ⚠️ NO HYPHEN |
| 108 | **manpower delete** | **man-power delete** | ⚠️ NO HYPHEN |
| 109 | **manpower edit** | **man-power edit** | ⚠️ NO HYPHEN |
| 110 | **manpower export** | **man-power export** | ⚠️ NO HYPHEN |
| 111 | **manpower manage** | **man-power manage** | ⚠️ NO HYPHEN |
| 112 | **manpower show** | **man-power show** | ⚠️ NO HYPHEN |
| 113 | **manpower-type create** | **man-power-type create** | ⚠️ NO HYPHEN |
| 114 | **manpower-type delete** | **man-power-type delete** | ⚠️ NO HYPHEN |
| 115 | **manpower-type edit** | **man-power-type edit** | ⚠️ NO HYPHEN |
| 116 | **manpower-type export** | **man-power-type export** | ⚠️ NO HYPHEN |
| 117 | **manpower-type manage** | **man-power-type manage** | ⚠️ NO HYPHEN |
| 118 | **manpower-type show** | **man-power-type show** | ⚠️ NO HYPHEN |
| 119 | material create | material create | OK |
| 120 | material delete | material delete | OK |
| 121 | material edit | material edit | OK |
| 122 | material export | material export | OK |
| 123 | material manage | material manage | OK |
| 124 | material show | material show | OK |
| 125 | **material-categories create** | **material-category create** | ⚠️ PLURAL → SINGULAR |
| 126 | **material-categories delete** | **material-category delete** | ⚠️ PLURAL → SINGULAR |
| 127 | **material-categories edit** | **material-category edit** | ⚠️ PLURAL → SINGULAR |
| 128 | **material-categories export** | **material-category export** | ⚠️ PLURAL → SINGULAR |
| 129 | **material-categories manage** | **material-category manage** | ⚠️ PLURAL → SINGULAR |
| 130 | **material-categories show** | **material-category show** | ⚠️ PLURAL → SINGULAR |
| 131 | material-transfer create | material-transfer create | OK |
| 132 | material-transfer delete | material-transfer delete | OK |
| 133 | material-transfer edit | material-transfer edit | OK |
| 134 | material-transfer export | material-transfer export | OK |
| 135 | material-transfer manage | material-transfer manage | OK |
| 136 | material-transfer show | material-transfer show | OK |
| 137 | material-unit create | material-unit create | OK |
| 138 | material-unit delete | material-unit delete | OK |
| 139 | material-unit edit | material-unit edit | OK |
| 140 | material-unit export | material-unit export | OK |
| 141 | material-unit manage | material-unit manage | OK |
| 142 | material-unit show | material-unit show | OK |
| 143 | notification template manage | notification template manage | OK |
| 144 | opening stock create | opening stock create | OK |
| 145 | opening stock delete | opening stock delete | OK |
| 146 | opening stock edit | opening stock edit | OK |
| 147 | opening stock manage | opening stock manage | OK |
| 148 | opening stock show | opening stock show | OK |
| 149 | other payment create | other payment create | OK |
| 150 | other payment delete | other payment delete | OK |
| 151 | other payment edit | other payment edit | OK |
| 152 | other payment manage | other payment manage | OK |
| 153 | paysliptype create | paysliptype create | OK |
| 154 | paysliptype delete | paysliptype delete | OK |
| 155 | paysliptype edit | paysliptype edit | OK |
| 156 | paysliptype manage | paysliptype manage | OK |
| 157 | pipeline create | pipeline create | OK |
| 158 | pipeline delete | pipeline delete | OK |
| 159 | pipeline edit | pipeline edit | OK |
| 160 | pipeline manage | pipeline manage | OK |
| 161 | pos add manage | pos add manage | OK |
| 162 | pos cart manage | pos cart manage | OK |
| 163 | pos dashboard manage | pos dashboard manage | OK |
| 164 | product&service create | product&service create | OK |
| 165 | product&service delete | product&service delete | OK |
| 166 | product&service edit | product&service edit | OK |
| 167 | product&service import | product&service import | OK |
| 168 | product&service manage | product&service manage | OK |
| 169 | project create | project create | OK |
| 170 | project delete | project delete | OK |
| 171 | project edit | project edit | OK |
| 172 | project import | project import | OK |
| 173 | project manage | project manage | OK |
| 174 | project show | project show | OK |
| 175 | project-document create | project-document create | OK |
| 176 | project-document delete | project-document delete | OK |
| 177 | project-document edit | project-document edit | OK |
| 178 | project-document export | project-document export | OK |
| 179 | project-document manage | project-document manage | OK |
| 180 | project-document show | project-document show | OK |
| 181 | project-file create | project-file create | OK |
| 182 | project-file delete | project-file delete | OK |
| 183 | project-file edit | project-file edit | OK |
| 184 | project-file export | project-file export | OK |
| 185 | project-file manage | project-file manage | OK |
| 186 | project-file show | project-file show | OK |
| 187 | promotion create | promotion create | OK |
| 188 | promotion delete | promotion delete | OK |
| 189 | promotion edit | promotion edit | OK |
| 190 | promotion manage | promotion manage | OK |
| 191 | proposal product delete | proposal product delete | OK |
| 192 | purchase create | purchase create | OK |
| 193 | purchase debitnote create | purchase debitnote create | OK |
| 194 | purchase debitnote delete | purchase debitnote delete | OK |
| 195 | purchase debitnote edit | purchase debitnote edit | OK |
| 196 | purchase payment create | purchase payment create | OK |
| 197 | purchase payment delete | purchase payment delete | OK |
| 198 | purchase product delete | purchase product delete | OK |
| 199 | **purchase-invoices create** | **purchase-invoice create** | ⚠️ PLURAL → SINGULAR |
| 200 | **purchase-invoices delete** | **purchase-invoice delete** | ⚠️ PLURAL → SINGULAR |
| 201 | **purchase-invoices edit** | **purchase-invoice edit** | ⚠️ PLURAL → SINGULAR |
| 202 | **purchase-invoices export** | **purchase-invoice export** | ⚠️ PLURAL → SINGULAR |
| 203 | **purchase-invoices manage** | **purchase-invoice manage** | ⚠️ PLURAL → SINGULAR |
| 204 | **purchase-invoices print** | **purchase-invoice print** | ⚠️ PLURAL → SINGULAR |
| 205 | **purchase-invoices show** | **purchase-invoice show** | ⚠️ PLURAL → SINGULAR |
| 206 | purchase-order create | purchase-order create | OK |
| 207 | purchase-order delete | purchase-order delete | OK |
| 208 | purchase-order edit | purchase-order edit | OK |
| 209 | purchase-order export | purchase-order export | OK |
| 210 | purchase-order manage | purchase-order manage | OK |
| 211 | purchase-order print | purchase-order print | OK |
| 212 | purchase-order show | purchase-order show | OK |
| 213 | report loss & profit manage | report loss & profit manage | OK |
| 214 | saturation deduction create | saturation deduction create | OK |
| 215 | saturation deduction delete | saturation deduction delete | OK |
| 216 | saturation deduction edit | saturation deduction edit | OK |
| 217 | saturation deduction manage | saturation deduction manage | OK |
| 218 | setsalary create | setsalary create | OK |
| 219 | setsalary edit | setsalary edit | OK |
| 220 | setsalary manage | setsalary manage | OK |
| 221 | setsalary pay slip manage | setsalary pay slip manage | OK |
| 222 | setsalary show | setsalary show | OK |
| 223 | sidebar banking manage | sidebar banking manage | OK |
| 224 | sidebar expanse manage | sidebar expanse manage | OK |
| 225 | sidebar hr admin manage | sidebar hr admin manage | OK |
| 226 | sidebar hrm report manage | sidebar hrm report manage | OK |
| 227 | sidebar income manage | sidebar income manage | OK |
| 228 | sidebar payroll manage | sidebar payroll manage | OK |
| 229 | site stock create | site stock create | OK |
| 230 | site stock delete | site stock delete | OK |
| 231 | site stock edit | site stock edit | OK |
| 232 | site stock export | site stock export | OK |
| 233 | site stock manage | site stock manage | OK |
| 234 | site stock show | site stock show | OK |
| 235 | source create | source create | OK |
| 236 | source delete | source delete | OK |
| 237 | source edit | source edit | OK |
| 238 | source manage | source manage | OK |
| 239 | stock ledger create | stock ledger create | OK |
| 240 | stock ledger delete | stock ledger delete | OK |
| 241 | stock ledger edit | stock ledger edit | OK |
| 242 | stock ledger export | stock ledger export | OK |
| 243 | stock ledger manage | stock ledger manage | OK |
| 244 | stock ledger show | stock ledger show | OK |
| 245 | stock-report add | stock-report add | OK |
| 246 | stock-report consume | stock-report consume | OK |
| 247 | stock-report export | stock-report export | OK |
| 248 | stock-report manage | stock-report manage | OK |
| 249 | stock-report transfer | stock-report transfer | OK |
| 250 | sub-task create | sub-task create | OK |
| 251 | sub-task delete | sub-task delete | OK |
| 252 | sub-task edit | sub-task edit | OK |
| 253 | sub-task manage | sub-task manage | OK |
| 254 | supplier create | supplier create | OK |
| 255 | supplier delete | supplier delete | OK |
| 256 | supplier edit | supplier edit | OK |
| 257 | supplier export | supplier export | OK |
| 258 | supplier manage | supplier manage | OK |
| 259 | supplier show | supplier show | OK |
| 260 | **supplier-categories create** | **supplier-category create** | ⚠️ PLURAL → SINGULAR |
| 261 | **supplier-categories delete** | **supplier-category delete** | ⚠️ PLURAL → SINGULAR |
| 262 | **supplier-categories edit** | **supplier-category edit** | ⚠️ PLURAL → SINGULAR |
| 263 | **supplier-categories export** | **supplier-category export** | ⚠️ PLURAL → SINGULAR |
| 264 | **supplier-categories manage** | **supplier-category manage** | ⚠️ PLURAL → SINGULAR |
| 265 | **supplier-categories show** | **supplier-category show** | ⚠️ PLURAL → SINGULAR |
| 266 | supplier-ledger report | supplier-ledger report | OK |
| 267 | task comment create | task comment create | OK |
| 268 | task comment delete | task comment delete | OK |
| 269 | task comment edit | task comment edit | OK |
| 270 | task comment manage | task comment manage | OK |
| 271 | task comment show | task comment show | OK |
| 272 | task file delete | task file delete | OK |
| 273 | task file manage | task file manage | OK |
| 274 | task file show | task file show | OK |
| 275 | taskly dashboard manage | taskly dashboard manage | OK |
| 276 | taskstage create | taskstage create | OK |
| 277 | taskstage delete | taskstage delete | OK |
| 278 | taskstage edit | taskstage edit | OK |
| 279 | taskstage manage | taskstage manage | OK |
| 280 | taskstage show | taskstage show | OK |
| 281 | tax bracket create | tax bracket create | OK |
| 282 | tax bracket delete | tax bracket delete | OK |
| 283 | tax bracket edit | tax bracket edit | OK |
| 284 | tax bracket manage | tax bracket manage | OK |
| 285 | tax rebate create | tax rebate create | OK |
| 286 | tax rebate delete | tax rebate delete | OK |
| 287 | tax rebate edit | tax rebate edit | OK |
| 288 | tax rebate manage | tax rebate manage | OK |
| 289 | tax threshold create | tax threshold create | OK |
| 290 | tax threshold delete | tax threshold delete | OK |
| 291 | tax threshold edit | tax threshold edit | OK |
| 292 | tax threshold manage | tax threshold manage | OK |
| 293 | team client remove | team client remove | OK |
| 294 | team member remove | team member remove | OK |
| 295 | terminationtype create | terminationtype create | OK |
| 296 | terminationtype delete | terminationtype delete | OK |
| 297 | terminationtype edit | terminationtype edit | OK |
| 298 | terminationtype manage | terminationtype manage | OK |
| 299 | tools-and-equipment create | tools-and-equipment create | OK |
| 300 | tools-and-equipment delete | tools-and-equipment delete | OK |
| 301 | tools-and-equipment edit | tools-and-equipment edit | OK |
| 302 | tools-and-equipment export | tools-and-equipment export | OK |
| 303 | tools-and-equipment manage | tools-and-equipment manage | OK |
| 304 | tools-and-equipment show | tools-and-equipment show | OK |
| 305 | tools-and-equipment transfer | tools-and-equipment transfer | OK |
| 306 | transfer create | transfer create | OK |
| 307 | transfer delete | transfer delete | OK |
| 308 | transfer edit | transfer edit | OK |
| 309 | transfer manage | transfer manage | OK |
| 310 | unit cerate | unit create | ⚠️ TYPO |
| 311 | unit delete | unit delete | OK |
| 312 | unit edit | unit edit | OK |
| 313 | unit manage | unit manage | OK |
| 314 | user chat manage | user chat manage | OK |
| 315 | user create | user create | OK |
| 316 | user delete | user delete | OK |
| 317 | user edit | user edit | OK |
| 318 | user import | user import | OK |
| 319 | user logs history | user logs history | OK |
| 320 | user profile manage | user profile manage | OK |
| 321 | vendor create | vendor create | OK |
| 322 | vendor delete | vendor delete | OK |
| 323 | vendor edit | vendor edit | OK |
| 324 | vendor import | vendor import | OK |
| 325 | vendor manage | vendor manage | OK |
| 326 | vendor show | vendor show | OK |
| 327 | warehouse create | warehouse create | OK |
| 328 | warehouse delete | warehouse delete | OK |
| 329 | warehouse edit | warehouse edit | OK |
| 330 | warehouse import | warehouse import | OK |
| 331 | warehouse manage | warehouse manage | OK |
| 332 | warehouse show | warehouse show | OK |

---

## Permissions Requiring Updates (SQL)

### 1. activities → activity
```sql
UPDATE permissions SET name = REPLACE(name, 'activities ', 'activity ') WHERE name LIKE 'activities %';
```

### 2. machinery-categories → machinery-category
```sql
UPDATE permissions SET name = REPLACE(name, 'machinery-categories ', 'machinery-category ') WHERE name LIKE 'machinery-categories %';
```

### 3. manpower → man-power (add hyphen)
```sql
UPDATE permissions SET name = REPLACE(name, 'manpower ', 'man-power ') WHERE name LIKE 'manpower %';
```

### 4. manpower-type → man-power-type (add hyphen)
```sql
UPDATE permissions SET name = REPLACE(name, 'manpower-type ', 'man-power-type ') WHERE name LIKE 'manpower-type %';
```

### 5. material-categories → material-category
```sql
UPDATE permissions SET name = REPLACE(name, 'material-categories ', 'material-category ') WHERE name LIKE 'material-categories %';
```

### 6. purchase-invoices → purchase-invoice
```sql
UPDATE permissions SET name = REPLACE(name, 'purchase-invoices ', 'purchase-invoice ') WHERE name LIKE 'purchase-invoices %';
```

### 7. supplier-categories → supplier-category
```sql
UPDATE permissions SET name = REPLACE(name, 'supplier-categories ', 'supplier-category ') WHERE name LIKE 'supplier-categories %';
```

### 8. unit cerate → unit create (typo fix)
```sql
UPDATE permissions SET name = 'unit create' WHERE name = 'unit cerate';
```

---

## Code Updates Required

The following permission strings in the code need to match the database after standardization:

| Resource | Current Code | After DB Update |
|----------|--------------|-----------------|
| activity | activity | activity |
| machinery-category | machinery-category | machinery-category |
| man-power | man-power | man-power |
| man-power-type | man-power-type | man-power-type |
| material-category | material-category | material-category |
| purchase-invoice | purchase-invoice | purchase-invoice |
| supplier-category | supplier-category | supplier-category |
| unit | unit | unit |

**Status: The code already uses the correct singular + kebab-case format. Only database needs updating.**
