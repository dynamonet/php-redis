-- Script for checking pending entries and claiming them
local rcall = redis.call
local stream = KEYS[1]
local group = KEYS[2]
local consumer = KEYS[3] -- name of the consumer that will reclaim pending messages
local minidle = tonumber(KEYS[4]) -- min-idle-time, in milliseconds
local count = tonumber(KEYS[5]) -- max entries to claim
local result = {}

local summary = rcall("XPENDING", stream, group)
local total = summary[1]

if total > 0 then
    local r = 1
    local pending = rcall("XPENDING", stream, group, "-", "+", total)
    for i, details in pairs(pending) do
        local id = details[1]
        local idle = details[3]
        if idle >= minidle then
            local reply = rcall("XCLAIM", stream, group, consumer, minidle, details[1])
            local msg = reply[1]
            result[r] = msg[1]
            result[r+1] = msg[2]
            r = r + 2
            if r / 2 > count then
                break
            end
        end
    end
end

return result