string[string][string] readCSVRoster(string filename) {
    import std.csv;
    import std.stdio;
    import std.algorithm;
    auto f = File(filename);
    auto result = csvReader!(string[string])(f.byLine.joiner("\n"), null);
    string[string][string] as_array;
    foreach (x; result) {
        as_array[x["id"]] = x;
    }
    return as_array;
}
