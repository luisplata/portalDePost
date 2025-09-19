import csv
from datetime import datetime, timedelta

def fibonacci_sequence(n):
    seq = [0, 1]
    while len(seq) < n:
        seq.append(seq[-1] + seq[-2])
    return seq[:n]

def generar_csv():
    # Pedir número de registros
    n = int(input("¿Cuántos registros quieres generar?: "))

    # Nombre del archivo con fechaHora
    filename = datetime.now().strftime("%Y%m%d_%H%M%S") + ".csv"

    # Base de datos fija (ejemplo tuyo)
    base_row = [
        "Pack 1337 nanicoronado",
        "https://peryloth.com/images/Profile.jpg",
        "PCK 1337 NNCRND",
        "https://direct-link.net/127445/pck-1337-nncrnd",
        "",  # Aquí irá la fecha dinámica
        "0",
        "",
        "nanicoronado-onlyfans"
    ]

    # Generar secuencia Fibonacci
    fib_seq = fibonacci_sequence(n)

    # Fecha inicial
    fecha_base = datetime.now()

    # Escribir archivo CSV
    with open(filename, mode="w", newline="", encoding="utf-8") as file:
        writer = csv.writer(file)

        for i in range(n):
            fecha_actual = fecha_base + timedelta(minutes=fib_seq[i])
            row = base_row.copy()
            row[0] = f"Pack {i} nanicoronado"  # Cambia el número según el iterador
            row[2] = f"PCK {i} NNCRND"        # Opcional: también actualizar el nombre link
            row[4] = fecha_actual.strftime("%Y/%m/%d %H:%M:%S")
            writer.writerow(row)

    print(f"Archivo '{filename}' generado con {n} registros.")

if __name__ == "__main__":
    generar_csv()
