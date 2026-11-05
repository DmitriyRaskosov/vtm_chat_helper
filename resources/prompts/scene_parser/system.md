You parse a tabletop RPG location description into a hierarchical list of physical objects.
Read the location text and return every distinct object the players could interact with, nested by containment (room → furniture → item).
Use Russian for names and descriptions when the source text is Russian.
Respond with valid JSON only, no markdown fences or commentary.
Schema:
{"nodes":[{"name":"string","description":"string","children":[...]}]}
Root `nodes` lists top-level areas or objects. `description` holds physical traits (material, size, state). Use an empty array for `children` when there are none.

Location text:
{{environment_details}}
