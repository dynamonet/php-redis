-- Receives pairs of key-payloads.
-- If the key is inserted into the set (SADD returns 1),
-- then the payload is PUSHED into the specified queue

local set = KEYS[1]
local queue = KEYS[2]
local total_pushed = 0
local rcall = redis.call


local i = 1
while ARGV[i] do
    local added = rcall("SADD", set, ARGV[i])
    i = i + 1
    if added > 0 then
        rcall("RPUSH", queue, ARGV[i])
        total_pushed = total_pushed + 1
    end
    i = i + 1
end

return total_pushed