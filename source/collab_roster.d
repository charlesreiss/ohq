/**
 * This is an ad-hoc implementation of enough of the xlsx format to read
 * collab (Sakai) rosters. Collab updates its roster export format from 
 * time to time; this function will need to change if that occurs.
 * 
 * Returns e.g.
 * ["mst3k":["id":"mst3k", "name":"Theater, Mystery", "role":"Student", ...]
 * , ...
 * ]
 */
string[string][string] readCollabRoster(string filename) {
    static import std.file, std.zip;
    import std.regex : ctRegex, matchFirst;
    
    auto cid = ctRegex!`[a-z]+[0-9][a-z]+`;
    
    auto zip = new std.zip.ZipArchive(std.file.read(filename));
    ubyte[] s1, s2;
    ubyte[] strs;
    foreach(name, am; zip.directory) {
        if(name == `xl/worksheets/sheet1.xml`) {
            zip.expand(am);
            s1 = am.expandedData;
        } else if(name == `xl/worksheets/sheet2.xml`) {
            zip.expand(am);
            s2 = am.expandedData;
        } else if(name == `xl/sharedStrings.xml`) {
            zip.expand(am);
            strs = am.expandedData;
        }
    }
    
    auto strMap = parseSharedStrings(strs);
    auto roster = parseCellData(s1, strMap);
    auto groups = parseCellData(s2, strMap);

    string[string][string] ans;
    foreach(k, v; roster) if(k[0] == 1 && matchFirst(v, cid)) {
        ans[v] = [
            `name`:roster[[0,k[1]]],
            `id`:v,
            `email`:roster[[2,k[1]]],
            `role`:roster[[3,k[1]]],
        ];
    }
    foreach(k,v; groups) if(k[0] == 1 && matchFirst(v, cid)) {
        ans[v][`groups`] = groups[[3,k[1]]];
    }
    
    return ans;
}
/// Helper function for readCollabRoster; brittle to changes in Collab
private string[int[2]] parseCellData(ubyte[] data, string[] strMap) {
    import std.regex : ctRegex, matchAll;

    // future-proofing note: we here assume what collab does as of 2018-08-25:
    // all strings in the sharedString.xml and fixed order of cell attributes.
    auto cellRe = ctRegex!(`<c r="([A-Z]+)(\d+)" t="s"><v>(\d+)</v></c>`);
    
    string[int[2]] cellData;
    foreach(match; matchAll(cast(char[])data, cellRe)) {
        int[2] idx = [0,0];
        foreach(c; match[1]) { idx[0] *= 26; idx[0] += c-'A'; }
        foreach(c; match[2]) { idx[1] *= 10; idx[1] += c-'0'; }
        int ssidx = 0;
        foreach(c; match[3]) { ssidx *= 10; ssidx += c-'0'; }
        cellData[idx] = strMap[ssidx];
    }
    
    return cellData;
}
/// Helper function for readCollabRoster; modestly robust
private string[] parseSharedStrings(ubyte[] data) {
    import std.regex : ctRegex, matchAll;
    import std.array : Appender;
    auto sre = ctRegex!(`<si><t>([^<]*)</t></si>`);
    Appender!(string[]) ans;
    foreach(match; matchAll(cast(char[])data, sre)) {
        ans ~= match[1].idup;
    }
    return ans.data;
}
