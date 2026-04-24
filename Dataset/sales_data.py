from __future__ import annotations

import argparse
import csv
import random
from datetime import date, timedelta


BRANCH_NAMES = [
    "chattogram", "dhanmondi", "motijheel",
    "uttara", "mirpur", "gulshan",
]

BRANCH_STATIC_VARIANTS = [
    "chattogram", "Chattogram", "CHATTOGRAM ", " Chattogram",
    "dhanmondi", "Dhanmondi ", "DHANMONDI", "Dhanmondi",
    "motijheel", "Motijheel", "MOTIJHEEL ", " motijheel",
    "uttara", "Uttara", "UTTARA ", "uttara ",
    "mirpur", "Mirpur ", "MIRPUR", " mirpur",
    "gulshan", "Gulshan", "GULSHAN ", " gulshan ",
]


def _random_mixed_case(name: str) -> str:
    chars = []
    for ch in name:
        if ch.isalpha() and random.random() < 0.35:
            chars.append(ch.upper() if ch.islower() else ch.lower())
        else:
            chars.append(ch)
    result = "".join(chars)
    pad = random.random()
    if pad < 0.15:
        result = " " + result
    elif pad < 0.30:
        result = result + " "
    elif pad < 0.35:
        result = " " + result + " "
    return result


def pick_branch() -> str:
    if random.random() < 0.35:
        return _random_mixed_case(random.choice(BRANCH_NAMES))
    return random.choice(BRANCH_STATIC_VARIANTS)


PRODUCTS = [
    ("Rice 5kg",            "Groceries"),
    ("Rice 25kg",           "Groceries"),
    ("Soyabean Oil 1L",     "Groceries"),
    ("Mustard Oil 1L",      "Groceries"),
    ("Sugar 1kg",           "Groceries"),
    ("Salt 1kg",            "Groceries"),
    ("Lentil (Masoor) 1kg", "Groceries"),
    ("Flour 2kg",           "Groceries"),
    ("Tea 500g",            "Groceries"),
    ("Detergent 1kg",       "Groceries"),
    ("Bath Soap",           "Groceries"),
    ("Shampoo 200ml",       "Groceries"),
    ("Toothpaste",          "Groceries"),
    ("Biscuit Pack",        "Groceries"),
    ("Milk Powder 500g",    "Groceries"),
    ("Onion 1kg",           "Groceries"),
    ("Potato 1kg",          "Groceries"),
    ("Garlic 500g",         "Groceries"),
    ("Ginger 500g",         "Groceries"),
    ("Chili Powder 200g",   "Groceries"),
    ("Turmeric 200g",       "Groceries"),
    ("LED Bulb 9W",         "Electronics"),
    ("Extension Cord",      "Electronics"),
    ("Rechargeable Fan",    "Electronics"),
    ("Electric Kettle",     "Electronics"),
    ("Iron",                "Electronics"),
    ("Lungi",               "Clothing"),
    ("Panjabi",             "Clothing"),
    ("T-Shirt",             "Clothing"),
    ("Saree",               "Clothing"),
    ("Socks Pair",          "Clothing"),
]

PRODUCTS_BENGALI = [
    ("চাল",   "Groceries"),
    ("ডাল",   "Groceries"),
    ("তেল",   "Groceries"),
    ("চিনি",  "Groceries"),
    ("লবণ",   "Groceries"),
    ("সাবান", "Groceries"),
]


def pick_product() -> tuple[str, str]:
    if random.random() < 0.03:
        return random.choice(PRODUCTS_BENGALI)
    return random.choice(PRODUCTS)


PAYMENT_METHODS = [
    "cash", "Cash", "CASH",
    "bKash", "Bkash", "BKASH", "bkash",
    "nagad", "Nagad", "NAGAD",
    "Card", "card", "CARD",
]

SALESPEOPLE = [
    "Rahim Uddin", "Karim Hossain", "Shafiq Ahmed", "Nusrat Jahan",
    "Tanvir Hasan", "Sabbir Rahman", "Mehedi Hasan", "Farhana Akter",
    "Arif Chowdhury", "Lima Khatun", "Jahid Islam", "Sumaiya Sultana",
]

CATEGORY_DIRTY = ["", "N/A", "-", "NULL", "n/a", "null"]


def format_date(d: date) -> str:
    style = random.choice(["dmy_slash", "ymd_dash", "mdy_dash"])
    if style == "dmy_slash":
        return d.strftime("%d/%m/%Y")
    if style == "ymd_dash":
        return d.strftime("%Y-%m-%d")
    return d.strftime("%m-%d-%Y")


def format_unit_price(price: float) -> str:
    whole = int(round(price))
    r = random.random()
    if r < 0.20:
        return f"{whole}"
    if r < 0.30:
        return f"৳{whole}"
    if r < 0.40:
        return f"৳{whole:,}"
    if r < 0.60:
        return f"{price:.2f}"
    if r < 0.75:
        return f"৳{price:.2f}"
    if r < 0.88:
        return f"৳{price:,.2f}"
    if r < 0.95:
        return f"  ৳{price:,.2f} "
    return f" {price:.2f} "


def format_discount(pct_int: int) -> str | float | int:
    style = random.choice(["int", "percent_str", "decimal"])
    if style == "int":
        return pct_int
    if style == "percent_str":
        return f"{pct_int}%"
    return round(pct_int / 100.0, 2)


def messify_product_name(name: str) -> str:
    roll = random.random()
    if roll < 0.10:
        return f"  {name}"
    if roll < 0.20:
        return f"{name} "
    if roll < 0.25:
        return f"  {name}  "
    return name


def pick_category(clean_category: str) -> str | None:
    if random.random() < 0.12:
        dirty = random.choice(CATEGORY_DIRTY)
        return None if dirty == "NULL" else dirty
    return clean_category


def pick_salesperson() -> str | None:
    r = random.random()
    if r < 0.05:
        return None
    if r < 0.08:
        return "NULL"
    if r < 0.10:
        return ""
    return random.choice(SALESPEOPLE)


START_DATE = date(2025, 12, 31)
END_DATE = date(2026, 4, 24)
DATE_SPAN_DAYS = (END_DATE - START_DATE).days

PRICE_BY_CATEGORY = {
    "Groceries":   (25.0,   1500.0),
    "Electronics": (150.0,  6500.0),
    "Clothing":    (200.0,  4500.0),
}


def random_date() -> date:
    return START_DATE + timedelta(days=random.randint(0, DATE_SPAN_DAYS))


def build_row(sale_id: int) -> dict:
    product_name, clean_category = pick_product()
    low, high = PRICE_BY_CATEGORY[clean_category]
    unit_price = round(random.uniform(low, high), 2)

    row = {
        "sale_id":        sale_id,
        "branch":         pick_branch(),
        "sale_date":      format_date(random_date()),
        "product_name":   messify_product_name(product_name),
        "category":       pick_category(clean_category),
        "quantity":       random.randint(1, 20),
        "unit_price":     format_unit_price(unit_price),
        "discount_pct":   format_discount(random.choice([0, 5, 5, 10, 10, 15, 20, 25])),
        "payment_method": random.choice(PAYMENT_METHODS),
        "salesperson":    pick_salesperson(),
    }

    if random.random() < 0.005:
        del row["salesperson"]

    return row


FIELDNAMES = [
    "sale_id", "branch", "sale_date", "product_name", "category",
    "quantity", "unit_price", "discount_pct", "payment_method", "salesperson",
]


def generate(rows: int, out_path: str, seed: int | None, duplicates: int) -> None:
    if seed is not None:
        random.seed(seed)

    total_unique = max(1, rows - duplicates)
    ragged_rows = 0

    with open(out_path, "w", encoding="utf-8-sig", newline="") as fp:
        writer = csv.writer(fp)
        writer.writerow(FIELDNAMES)

        generated_rows: list[dict] = []

        for sid in range(1, total_unique + 1):
            row = build_row(sid)
            _emit_row(writer, row)
            if len(row) < len(FIELDNAMES):
                ragged_rows += 1

            if len(generated_rows) < 5000:
                generated_rows.append(row)
            elif random.random() < 0.01:
                generated_rows[random.randint(0, 4999)] = row

        for _ in range(duplicates):
            source = random.choice(generated_rows)
            dup = build_row(source["sale_id"])
            if random.random() < 0.5:
                dup = dict(source)
            _emit_row(writer, dup)
            if len(dup) < len(FIELDNAMES):
                ragged_rows += 1

    print(
        f"Wrote {rows:,} rows (including {duplicates} duplicate sale_ids "
        f"and {ragged_rows} ragged rows missing the salesperson column) to {out_path}"
    )


def _emit_row(writer, row: dict) -> None:
    values = []
    for key in FIELDNAMES:
        if key not in row:
            break
        v = row[key]
        values.append("" if v is None else v)
    writer.writerow(values)


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Generate messy wholesale sales CSV.")
    p.add_argument("--rows", type=int, default=20000)
    p.add_argument("--duplicates", type=int, default=250)
    p.add_argument("--out", default="sales_data.csv")
    p.add_argument("--seed", type=int, default=None)
    return p.parse_args()


if __name__ == "__main__":
    args = parse_args()
    generate(args.rows, args.out, args.seed, args.duplicates)
