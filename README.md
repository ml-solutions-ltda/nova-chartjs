# Nova ChartJS - Laravel Nova Package

A Laravel Nova Dashboard with Chart JS | See [:blue_book:Documentation Page](https://coroo.github.io/nova-chartjs/)

> [!NOTE]
> 👋 Welcome to Nova-ChartJS! We believe that great software is built through collaboration, and we invite you to be a part of it. Whether you're a developer, designer, tester, or just someone passionate about the project, there are many ways to contribute.
>
> [Click here to contribute!](https://github.com/coroo/nova-chartjs/blob/master/CONTRIBUTING.md#how-to-contribute)

![Chart JS Integration in Action](https://raw.githubusercontent.com/coroo/chart-js-integration/gh-pages/assets/img/chart-js-integration.gif)

![Continues Integration](https://github.com/coroo/nova-chartjs/workflows/ci/badge.svg?branch=master)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mlsolutions/chart-js-integration)](https://packagist.org/packages/mlsolutions/chart-js-integration)
[![Total Downloads](https://img.shields.io/packagist/dt/mlsolutions/chart-js-integration)](https://packagist.org/packages/mlsolutions/chart-js-integration)
[![License](https://img.shields.io/github/languages/top/coroo/nova-chartjs)](https://packagist.org/packages/mlsolutions/chart-js-integration)
[![State Status](https://img.shields.io/github/deployments/coroo/nova-chartjs/github-pages)](https://packagist.org/packages/mlsolutions/chart-js-integration)
[![License](https://img.shields.io/packagist/l/mlsolutions/chart-js-integration)](https://github.com/coroo/chart-js-integration/blob/master/LICENSE)

[![Listed in Awesome ChartJS](https://camo.githubusercontent.com/13c4e50d88df7178ae1882a203ed57b641674f94/68747470733a2f2f63646e2e7261776769742e636f6d2f73696e647265736f726875732f617765736f6d652f643733303566333864323966656437386661383536353265336136336531353464643865383832392f6d656469612f62616467652e737667)](https://github.com/chartjs/awesome#integrations)
[![License](https://img.shields.io/github/stars/coroo/nova-chartjs?style=social)](https://github.com/coroo/nova-chartjs/stargazers)

## Installation & Documentation

:mortar_board: For better experiences, we moved documentation to : __https://coroo.github.io/nova-chartjs/__

## Security & Input Validation

Recent versions validate chart query inputs more strictly to avoid SQL injection and ambiguous query behavior.

- `model` must be an existing Eloquent model class.
- `col_xaxis`, `join` columns, and filter keys must be valid identifiers (for example: `orders.created_at`).
- Supported filter operators: `=`, `!=`, `<>`, `>`, `>=`, `<`, `<=`, `LIKE`, `NOT LIKE`, `ILIKE`, `NOT ILIKE`, `IS NULL`, `IS NOT NULL`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`.
- `sum` accepts only numeric values or valid column identifiers.

If your dashboard used raw SQL expressions in `sum`, `col_xaxis`, or filter keys/operators, adjust it to the supported format above.

## Running Tests

This package includes integration tests for the API endpoints, including `NOT IN`, `BETWEEN`, `join`, `uom=day`, and cache key isolation.

```bash
composer install
composer test
```

## ChangeLog

Please see [CHANGELOG](https://github.com/coroo/chart-js-integration/blob/master/CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](https://github.com/coroo/chart-js-integration/blob/master/LICENSE) for more information.

