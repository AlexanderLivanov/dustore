import hashlib

# Исходный массив
data = [
    {"TerminalKey": "1768985142695DEMO"},
    {"Amount": "19900"},
    {"OrderId": "order_6970d7d606888"},
    {"Description": "\u041e\u043f\u043b\u0430\u0442\u0430 \u0437\u0430\u043a\u0430\u0437\u0430 #order_6970d7d606888"},
    {"Password": "2$XMgboHTiyRtE*d"}
]

# Сортировка по ключу
sorted_data = sorted(data, key=lambda x: list(x.keys())[0])

# Конкатенация значений
concat_values = ''.join(list(d.values())[0] for d in sorted_data)

# Вычисление SHA-256
hash_result = hashlib.sha256(concat_values.encode('utf-8')).hexdigest()

print("Строка для хеширования:", concat_values)
print("SHA-256:", hash_result)
